$ErrorActionPreference = 'Stop'

$projectDir = $PSScriptRoot
$sourceFile = Join-Path $projectDir 'src\main.cpp'
$executable = Join-Path $projectDir 'device-simulator.exe'
$compilerCommand = Get-Command g++ -ErrorAction SilentlyContinue

if ($null -eq $compilerCommand) {
    throw 'g++ non è nel PATH. Installa MinGW-w64 oppure aggiungi la sua cartella bin al PATH.'
}

& $compilerCommand.Source -std=c++17 -O2 -Wall -Wextra $sourceFile -o $executable -lwinhttp -lbcrypt
if ($LASTEXITCODE -ne 0) {
    throw "Compilazione C++ fallita (codice $LASTEXITCODE)."
}

& $executable @args
exit $LASTEXITCODE
