<?php

declare(strict_types=1);

namespace app\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * Specializzazione di partita per il gioco Freccette.
 */
class DartsMatch extends GameMatch
{
    public const GAME_CODE = 'darts';

    public const SCENARIO_SEARCH = 'search';

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_SEARCH] = ['name', 'status', 'max_players', 'created_at'];

        return $scenarios;
    }

    public static function findDarts(): ActiveQuery
    {
        return static::find()
            ->innerJoinWith('game')
            ->andWhere(['{{%tbl_game}}.code' => self::GAME_CODE]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            [['device_id'], 'required'],
            [['device_id'], 'filter', 'filter' => static fn ($value) => $value === '' ? null : $value],
            [['device_id'], 'integer'],
            [['device_id'], 'exist', 'skipOnError' => true, 'targetClass' => Device::class, 'targetAttribute' => ['device_id' => 'id']],
            [['device_id'], 'validateDeviceGame'],
            [['created_at'], 'date', 'format' => 'php:Y-m-d', 'on' => self::SCENARIO_SEARCH],
        ];
    }

    public function validateDeviceGame(string $attribute): void
    {
        if ($this->hasErrors($attribute) || $this->$attribute === null || $this->$attribute === '') {
            return;
        }

        $device = Device::findOne((int) $this->$attribute);
        if ($device !== null && (int) $device->game_id !== (int) $this->game_id) {
            $this->addError($attribute, 'Il dispositivo selezionato non è compatibile con le freccette.');
        }
    }

    public static function findEnabledGame(): ?Game
    {
        $game = Game::find()
            ->where(['code' => self::GAME_CODE, 'enabled' => true])
            ->one();

        return $game;
    }

    /**
     * Crea il provider per l'elenco amministrativo delle partite di freccette.
     *
     * @param array<string, mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        $this->scenario = self::SCENARIO_SEARCH;
        $query = self::findDarts()->with('device')->orderBy([
            '{{%tbl_matches}}.created_at' => SORT_DESC,
            '{{%tbl_matches}}.id' => SORT_DESC,
        ]);
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query
            ->andFilterWhere(['{{%tbl_matches}}.status' => $this->status])
            ->andFilterWhere(['{{%tbl_matches}}.max_players' => $this->max_players])
            ->andFilterWhere(['like', '{{%tbl_matches}}.name', $this->name]);

        if ($this->created_at !== null && $this->created_at !== '') {
            $query->andWhere([
                'between',
                '{{%tbl_matches}}.created_at',
                $this->created_at . ' 00:00:00',
                $this->created_at . ' 23:59:59',
            ]);
        }

        return $dataProvider;
    }
}
