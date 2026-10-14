#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <winhttp.h>
#include <conio.h>

#include <algorithm>
#include <array>
#include <chrono>
#include <cstdint>
#include <functional>
#include <iomanip>
#include <iostream>
#include <map>
#include <random>
#include <sstream>
#include <stdexcept>
#include <string>
#include <thread>
#include <variant>
#include <vector>

namespace {

struct Json {
    using Array = std::vector<Json>;
    using Object = std::map<std::string, Json>;
    using Value = std::variant<std::nullptr_t, bool, std::int64_t, double, std::string, Array, Object>;
    Value value = nullptr;

    const Json& get(const std::string& key) const {
        static const Json missing;
        if (const auto* object = std::get_if<Object>(&value)) {
            const auto found = object->find(key);
            if (found != object->end()) return found->second;
        }
        return missing;
    }

    const Json& at(std::size_t index) const {
        static const Json missing;
        if (const auto* array = std::get_if<Array>(&value); array && index < array->size()) return (*array)[index];
        return missing;
    }

    const Array* array() const { return std::get_if<Array>(&value); }
    bool isNull() const { return std::holds_alternative<std::nullptr_t>(value); }

    std::string string(const std::string& fallback = "") const {
        if (const auto* text = std::get_if<std::string>(&value)) return *text;
        if (const auto* number = std::get_if<std::int64_t>(&value)) return std::to_string(*number);
        if (const auto* number = std::get_if<double>(&value)) return std::to_string(*number);
        if (const auto* boolean = std::get_if<bool>(&value)) return *boolean ? "true" : "false";
        return fallback;
    }

    std::int64_t integer(std::int64_t fallback = 0) const {
        if (const auto* number = std::get_if<std::int64_t>(&value)) return *number;
        if (const auto* number = std::get_if<double>(&value)) return static_cast<std::int64_t>(*number);
        return fallback;
    }
};

class JsonParser {
public:
    explicit JsonParser(const std::string& source) : source_(source) {}

    Json parse() {
        skipWhitespace();
        Json result = parseValue();
        skipWhitespace();
        if (position_ != source_.size()) fail("testo dopo il valore JSON");
        return result;
    }

private:
    const std::string& source_;
    std::size_t position_ = 0;

    [[noreturn]] void fail(const std::string& message) const {
        throw std::runtime_error("JSON non valido: " + message);
    }

    void skipWhitespace() {
        while (position_ < source_.size() && (source_[position_] == ' ' || source_[position_] == '\n'
                || source_[position_] == '\r' || source_[position_] == '\t')) ++position_;
    }

    char take() {
        if (position_ >= source_.size()) fail("fine input inattesa");
        return source_[position_++];
    }

    bool consume(char expected) {
        if (position_ < source_.size() && source_[position_] == expected) {
            ++position_;
            return true;
        }
        return false;
    }

    Json parseValue() {
        skipWhitespace();
        if (position_ >= source_.size()) fail("valore mancante");
        const char current = source_[position_];
        if (current == '"') return Json{parseString()};
        if (current == '{') return parseObject();
        if (current == '[') return parseArray();
        if (current == 't') return parseLiteral("true", Json{true});
        if (current == 'f') return parseLiteral("false", Json{false});
        if (current == 'n') return parseLiteral("null", Json{nullptr});
        if (current == '-' || (current >= '0' && current <= '9')) return parseNumber();
        fail("valore inatteso");
    }

    Json parseLiteral(const std::string& literal, Json result) {
        if (source_.compare(position_, literal.size(), literal) != 0) fail("literal non valido");
        position_ += literal.size();
        return result;
    }

    static void appendUtf8(std::string& output, unsigned int codepoint) {
        if (codepoint <= 0x7f) output.push_back(static_cast<char>(codepoint));
        else if (codepoint <= 0x7ff) {
            output.push_back(static_cast<char>(0xc0 | (codepoint >> 6)));
            output.push_back(static_cast<char>(0x80 | (codepoint & 0x3f)));
        } else if (codepoint <= 0xffff) {
            output.push_back(static_cast<char>(0xe0 | (codepoint >> 12)));
            output.push_back(static_cast<char>(0x80 | ((codepoint >> 6) & 0x3f)));
            output.push_back(static_cast<char>(0x80 | (codepoint & 0x3f)));
        } else {
            output.push_back(static_cast<char>(0xf0 | (codepoint >> 18)));
            output.push_back(static_cast<char>(0x80 | ((codepoint >> 12) & 0x3f)));
            output.push_back(static_cast<char>(0x80 | ((codepoint >> 6) & 0x3f)));
            output.push_back(static_cast<char>(0x80 | (codepoint & 0x3f)));
        }
    }

    unsigned int parseHex4() {
        unsigned int value = 0;
        for (int i = 0; i < 4; ++i) {
            const char digit = take();
            value <<= 4;
            if (digit >= '0' && digit <= '9') value |= static_cast<unsigned int>(digit - '0');
            else if (digit >= 'a' && digit <= 'f') value |= static_cast<unsigned int>(digit - 'a' + 10);
            else if (digit >= 'A' && digit <= 'F') value |= static_cast<unsigned int>(digit - 'A' + 10);
            else fail("escape Unicode non valido");
        }
        return value;
    }

    std::string parseString() {
        if (take() != '"') fail("stringa attesa");
        std::string output;
        while (position_ < source_.size()) {
            const unsigned char current = static_cast<unsigned char>(take());
            if (current == '"') return output;
            if (current < 0x20) fail("carattere di controllo in stringa");
            if (current != '\\') {
                output.push_back(static_cast<char>(current));
                continue;
            }
            const char escaped = take();
            switch (escaped) {
                case '"': output.push_back('"'); break;
                case '\\': output.push_back('\\'); break;
                case '/': output.push_back('/'); break;
                case 'b': output.push_back('\b'); break;
                case 'f': output.push_back('\f'); break;
                case 'n': output.push_back('\n'); break;
                case 'r': output.push_back('\r'); break;
                case 't': output.push_back('\t'); break;
                case 'u': {
                    unsigned int codepoint = parseHex4();
                    if (codepoint >= 0xd800 && codepoint <= 0xdbff && source_.compare(position_, 2, "\\u") == 0) {
                        position_ += 2;
                        const unsigned int low = parseHex4();
                        if (low < 0xdc00 || low > 0xdfff) fail("surrogate Unicode non valido");
                        codepoint = 0x10000 + ((codepoint - 0xd800) << 10) + (low - 0xdc00);
                    }
                    appendUtf8(output, codepoint);
                    break;
                }
                default: fail("escape stringa non valido");
            }
        }
        fail("stringa non terminata");
    }

    Json parseObject() {
        take();
        Json::Object result;
        skipWhitespace();
        if (consume('}')) return Json{result};
        while (true) {
            skipWhitespace();
            if (position_ >= source_.size() || source_[position_] != '"') fail("chiave oggetto attesa");
            std::string key = parseString();
            skipWhitespace();
            if (take() != ':') fail("':' atteso");
            result.emplace(std::move(key), parseValue());
            skipWhitespace();
            if (consume('}')) break;
            if (take() != ',') fail("',' attesa");
        }
        return Json{result};
    }

    Json parseArray() {
        take();
        Json::Array result;
        skipWhitespace();
        if (consume(']')) return Json{result};
        while (true) {
            result.push_back(parseValue());
            skipWhitespace();
            if (consume(']')) break;
            if (take() != ',') fail("',' attesa");
        }
        return Json{result};
    }

    Json parseNumber() {
        const std::size_t start = position_;
        consume('-');
        if (consume('0')) {
            // Uno zero iniziale non può essere seguito da altre cifre.
        } else {
            if (position_ >= source_.size() || source_[position_] < '1' || source_[position_] > '9') fail("numero non valido");
            while (position_ < source_.size() && source_[position_] >= '0' && source_[position_] <= '9') ++position_;
        }
        bool isDecimal = false;
        if (consume('.')) {
            isDecimal = true;
            if (position_ >= source_.size() || source_[position_] < '0' || source_[position_] > '9') fail("decimali non validi");
            while (position_ < source_.size() && source_[position_] >= '0' && source_[position_] <= '9') ++position_;
        }
        if (position_ < source_.size() && (source_[position_] == 'e' || source_[position_] == 'E')) {
            isDecimal = true;
            ++position_;
            if (position_ < source_.size() && (source_[position_] == '+' || source_[position_] == '-')) ++position_;
            if (position_ >= source_.size() || source_[position_] < '0' || source_[position_] > '9') fail("esponente non valido");
            while (position_ < source_.size() && source_[position_] >= '0' && source_[position_] <= '9') ++position_;
        }
        const std::string raw = source_.substr(start, position_ - start);
        try {
            if (isDecimal) return Json{std::stod(raw)};
            return Json{static_cast<std::int64_t>(std::stoll(raw))};
        } catch (...) {
            fail("numero fuori intervallo");
        }
    }
};

std::wstring widen(const std::string& text) {
    if (text.empty()) return {};
    const int length = MultiByteToWideChar(CP_UTF8, 0, text.data(), static_cast<int>(text.size()), nullptr, 0);
    if (length <= 0) throw std::runtime_error("Conversione URL UTF-8 non riuscita.");
    std::wstring result(static_cast<std::size_t>(length), L'\0');
    MultiByteToWideChar(CP_UTF8, 0, text.data(), static_cast<int>(text.size()), result.data(), length);
    return result;
}

class WinHttpClient {
public:
    explicit WinHttpClient(const std::string& baseUrl) {
        const std::wstring url = widen(baseUrl);
        URL_COMPONENTS parts{};
        parts.dwStructSize = sizeof(parts);
        parts.dwSchemeLength = static_cast<DWORD>(-1);
        parts.dwHostNameLength = static_cast<DWORD>(-1);
        parts.dwUrlPathLength = static_cast<DWORD>(-1);
        parts.dwExtraInfoLength = static_cast<DWORD>(-1);
        if (!WinHttpCrackUrl(url.c_str(), 0, 0, &parts)) throw std::runtime_error("URL server non valido.");

        host_.assign(parts.lpszHostName, parts.dwHostNameLength);
        port_ = parts.nPort;
        secure_ = parts.nScheme == INTERNET_SCHEME_HTTPS;
        basePath_.assign(parts.lpszUrlPath, parts.dwUrlPathLength);
        while (!basePath_.empty() && basePath_.back() == L'/') basePath_.pop_back();
        if (basePath_ == L"/") basePath_.clear();

        session_ = WinHttpOpen(L"CGP-Darts-Simulator/1.0", WINHTTP_ACCESS_TYPE_DEFAULT_PROXY,
                               WINHTTP_NO_PROXY_NAME, WINHTTP_NO_PROXY_BYPASS, 0);
        if (!session_) throw std::runtime_error("WinHTTP non disponibile.");
        WinHttpSetTimeouts(session_, 5000, 5000, 10000, 10000);
    }

    ~WinHttpClient() {
        if (session_) WinHttpCloseHandle(session_);
    }

    Json get(const std::string& route, const std::string& token = "") {
        return request(L"GET", route, "", token);
    }

    Json post(const std::string& route, const std::string& body, const std::string& token = "",
              const std::string& idempotencyKey = "") {
        return request(L"POST", route, body, token, idempotencyKey);
    }

private:
    std::wstring host_;
    std::wstring basePath_;
    INTERNET_PORT port_ = 0;
    bool secure_ = false;
    HINTERNET session_ = nullptr;

    Json request(const std::wstring& method, const std::string& route, const std::string& body,
                 const std::string& token, const std::string& idempotencyKey = "") {
        HINTERNET connection = WinHttpConnect(session_, host_.c_str(), port_, 0);
        if (!connection) throw std::runtime_error("Connessione al server non riuscita.");

        std::wstring path = basePath_ + L"/index.php?r=" + widen(route);
        HINTERNET requestHandle = WinHttpOpenRequest(connection, method.c_str(), path.c_str(), nullptr,
                WINHTTP_NO_REFERER, WINHTTP_DEFAULT_ACCEPT_TYPES, secure_ ? WINHTTP_FLAG_SECURE : 0);
        if (!requestHandle) {
            WinHttpCloseHandle(connection);
            throw std::runtime_error("Impossibile preparare la richiesta HTTP.");
        }

        std::wstring headers = L"Accept: application/json\r\n";
        if (!body.empty()) headers += L"Content-Type: application/json; charset=utf-8\r\n";
        if (!token.empty()) headers += L"Authorization: Bearer " + widen(token) + L"\r\n";
        if (!idempotencyKey.empty()) headers += L"Idempotency-Key: " + widen(idempotencyKey) + L"\r\n";

        const BOOL sent = WinHttpSendRequest(requestHandle, headers.c_str(), static_cast<DWORD>(headers.size()),
                body.empty() ? WINHTTP_NO_REQUEST_DATA : const_cast<char*>(body.data()),
                static_cast<DWORD>(body.size()), static_cast<DWORD>(body.size()), 0);
        if (!sent || !WinHttpReceiveResponse(requestHandle, nullptr)) {
            WinHttpCloseHandle(requestHandle);
            WinHttpCloseHandle(connection);
            throw std::runtime_error("Richiesta HTTP non riuscita.");
        }

        DWORD status = 0;
        DWORD statusSize = sizeof(status);
        WinHttpQueryHeaders(requestHandle, WINHTTP_QUERY_STATUS_CODE | WINHTTP_QUERY_FLAG_NUMBER,
                            WINHTTP_HEADER_NAME_BY_INDEX, &status, &statusSize, WINHTTP_NO_HEADER_INDEX);
        std::string response;
        DWORD available = 0;
        do {
            available = 0;
            if (!WinHttpQueryDataAvailable(requestHandle, &available)) break;
            if (available == 0) break;
            const std::size_t offset = response.size();
            response.resize(offset + available);
            DWORD read = 0;
            if (!WinHttpReadData(requestHandle, response.data() + offset, available, &read)) {
                response.resize(offset);
                break;
            }
            response.resize(offset + read);
        } while (available > 0);

        WinHttpCloseHandle(requestHandle);
        WinHttpCloseHandle(connection);
        Json parsed;
        try {
            parsed = JsonParser(response).parse();
        } catch (const std::exception& exception) {
            throw std::runtime_error("Risposta del server non leggibile: " + std::string(exception.what()));
        }
        if (status < 200 || status >= 300) {
            throw std::runtime_error("HTTP " + std::to_string(status) + ": " + parsed.get("error").string("richiesta rifiutata"));
        }
        return parsed;
    }
};

std::string newUuid() {
    std::array<unsigned char, 16> bytes{};
    std::random_device random;
    for (auto& byte : bytes) byte = static_cast<unsigned char>(random());
    bytes[6] = static_cast<unsigned char>((bytes[6] & 0x0f) | 0x40);
    bytes[8] = static_cast<unsigned char>((bytes[8] & 0x3f) | 0x80);
    std::ostringstream value;
    value << std::hex << std::setfill('0');
    for (std::size_t i = 0; i < bytes.size(); ++i) {
        if (i == 4 || i == 6 || i == 8 || i == 10) value << '-';
        value << std::setw(2) << static_cast<unsigned int>(bytes[i]);
    }
    return value.str();
}

std::string jsonIntegerField(const std::string& name, int value) {
    return "\"" + name + "\":" + std::to_string(value);
}

std::string summarize(const Json& state) {
    const Json& match = state.get("match");
    std::string result = match.get("name").string("Partita") + " — " + match.get("status").string("SCONOSCIUTO");
    if (const auto* players = state.get("players").array(); players && !players->empty()) {
        result += " | ";
        for (std::size_t index = 0; index < players->size(); ++index) {
            if (index > 0) result += " · ";
            const Json& player = (*players)[index];
            result += player.get("username").string() + ": " + player.get("remainingScore").string("0");
        }
    }
    const Json& turn = state.get("turn");
    const Json& player = turn.get("player");
    if (!player.isNull()) {
        result += " | turno: " + player.get("username").string() + " ("
                + turn.get("dartsRemaining").string("0") + " freccette)";
    }
    const Json& winner = state.get("winner");
    if (!winner.isNull()) result += " | vincitore: " + winner.get("username").string();
    return result;
}

int readInt(const std::string& prompt) {
    while (true) {
        std::cout << prompt;
        std::string line;
        if (!std::getline(std::cin, line)) throw std::runtime_error("Input terminato.");
        try {
            std::size_t consumed = 0;
            const int result = std::stoi(line, &consumed);
            if (consumed == line.size()) return result;
        } catch (...) {
            // Mostra un messaggio uniforme per input non numerici.
        }
        std::cout << "Inserisci un numero intero.\n";
    }
}

std::string recordThrow(WinHttpClient& http, const std::string& token) {
    Json state = http.get("api/darts-api/state", token);
    const std::string status = state.get("match").get("status").string();
    if (status != "RUNNING") {
        std::cout << "La partita non è in corso (stato: " << (status.empty() ? "nessuna partita" : status) << ").\n";
        return summarize(state);
    }
    const Json& turn = state.get("turn");
    const Json& player = turn.get("player");
    if (player.isNull()) {
        std::cout << "Il server non ha determinato il player di turno.\n";
        return summarize(state);
    }
    std::cout << "Tiro per " << player.get("username").string() << " — "
              << player.get("remainingScore").string("0") << " punti rimanenti, "
              << turn.get("dartsRemaining").string("0") << " freccette disponibili.\n";
    const int sector = readInt("Settore (0 mancato, 1-20, 25 bull): ");
    const int multiplier = sector == 0 ? 1 : readInt("Moltiplicatore (1-3; bull: 1 o 2): ");
    Json response = http.post("api/darts-api/throw", "{" + jsonIntegerField("sector", sector) + ","
            + jsonIntegerField("multiplier", multiplier) + "}", token, newUuid());
    const Json& result = response.get("throw");
    std::cout << "Tiro registrato: " << result.get("sector").string() << " × "
              << result.get("multiplier").string() << " = " << result.get("score").string()
              << " punti (" << result.get("result").string() << ").\n";
    const std::string current = summarize(http.get("api/darts-api/state", token));
    std::cout << current << '\n';
    return current;
}

std::string readCommand(const std::function<bool()>& pollState) {
    std::string command;
    std::cout << "> " << std::flush;
    auto nextPoll = std::chrono::steady_clock::now() + std::chrono::seconds(2);
    while (true) {
        if (std::chrono::steady_clock::now() >= nextPoll) {
            const bool changed = pollState();
            nextPoll = std::chrono::steady_clock::now() + std::chrono::seconds(2);
            if (changed) std::cout << "> " << command << std::flush;
        }
        if (_kbhit()) {
            const int character = _getch();
            if (character == '\r' || character == '\n') {
                std::cout << '\n';
                return command;
            }
            if (character == 8) {
                if (!command.empty()) {
                    command.pop_back();
                    std::cout << "\b \b" << std::flush;
                }
            } else if (character >= 32 && character <= 126) {
                command.push_back(static_cast<char>(character));
                std::cout << static_cast<char>(character) << std::flush;
            }
        }
        std::this_thread::sleep_for(std::chrono::milliseconds(100));
    }
}

void run(const std::string& serverUrl) {
    WinHttpClient http(serverUrl);
    const Json response = http.get("api/darts-api/devices");
    const Json& devicesJson = response.get("devices");
    const auto* devices = devicesJson.array();
    if (!devices || devices->empty()) throw std::runtime_error("Il server non ha dispositivi Freccette abilitati.");

    std::cout << "\nDispositivi disponibili:\n";
    for (const Json& device : *devices) {
        std::cout << "  " << device.get("id").string() << ") " << device.get("name").string() << '\n';
    }
    const int deviceId = readInt("Seleziona ID dispositivo: ");
    Json session = http.post("api/darts-api/session", "{" + jsonIntegerField("device_id", deviceId) + "}");
    const std::string token = session.get("access_token").string();
    if (token.empty()) throw std::runtime_error("Il server non ha restituito il token del dispositivo.");
    std::cout << "Dispositivo selezionato: " << session.get("device").get("name").string()
              << " (ID " << session.get("device").get("id").string() << ").\n"
              << "Il token resta in memoria e non viene salvato su disco.\n"
              << "Comandi: t = inserisci tiro, s = aggiorna stato, q = esci.\n";

    std::string lastState;
    auto poll = [&]() {
        bool changed = false;
        try {
            const std::string current = summarize(http.get("api/darts-api/state", token));
            if (current != lastState) {
                std::cout << "\n[Stato partita] " << current << '\n';
                lastState = current;
                changed = true;
            }
        } catch (const std::exception& exception) {
            const std::string error = exception.what();
            if (error != lastState) {
                std::cout << "\n[Connessione] " << error << '\n';
                lastState = error;
                changed = true;
            }
        }
        return changed;
    };
    poll();
    while (true) {
        const std::string command = readCommand(poll);
        if (command == "q" || command == "Q") break;
        if (command == "t" || command == "T") {
            try {
                lastState = recordThrow(http, token);
            } catch (const std::exception& exception) {
                std::cout << "Errore: " << exception.what() << '\n';
            }
        } else if (command == "s" || command == "S") {
            try {
                const std::string current = summarize(http.get("api/darts-api/state", token));
                std::cout << current << '\n';
                lastState = current;
            } catch (const std::exception& exception) {
                std::cout << "Errore: " << exception.what() << '\n';
            }
        } else if (!command.empty()) {
            std::cout << "Comando non riconosciuto. Usa t, s o q.\n";
        }
    }
    std::cout << "Sessione terminata; il token non è stato salvato.\n";
}

} // namespace

int main(int argc, char* argv[]) {
    try {
        SetConsoleOutputCP(CP_UTF8);
        SetConsoleCP(CP_UTF8);
        std::cout << "Connected Games Platform - simulatore Freccette\n";
        std::string serverUrl;
        if (argc > 1) {
            serverUrl = argv[1];
        } else {
            std::cout << "URL base server (es. http://127.0.0.1:8080): ";
            std::getline(std::cin, serverUrl);
        }
        if (serverUrl.empty()) throw std::runtime_error("URL server mancante.");
        run(serverUrl);
        return 0;
    } catch (const std::exception& exception) {
        std::cerr << "Errore: " << exception.what() << '\n';
        return 1;
    }
}
