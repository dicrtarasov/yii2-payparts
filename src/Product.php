<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 20:34:08
 */

declare(strict_types = 1);
namespace dicr\payparts;

use yii\base\Model;

/**
 * Данные товара.
 *
 * @property-read float $sum сумма
 * @property-read array $data данные JSON
 */
class Product extends Model
{
    /** @var string */
    public $name;

    /** @var int */
    public $count;

    /** @var float */
    public $price;

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            ['name', 'trim'],
            ['name', 'required'],
            ['name', 'string', 'max' => 128],

            ['count', 'required'],
            ['count', 'integer', 'min' => 1],
            ['count', 'filter', 'filter' => 'intval'],

            ['price', 'required'],
            ['price', 'number', 'min' => 0.01],
            ['price', 'filter', 'filter' => static function ($price) {
                return (float)sprintf('%.2f', $price);
            }]
        ];
    }

    /**
     * Сумма.
     *
     * @return float
     */
    public function getSum(): float
    {
        return $this->price * $this->count;
    }

    /**
     * Данные JSON.
     *
     * @return array
     */
    public function getData(): array
    {
        return [
            'name' => $this->name,
            'price' => sprintf('%.2f', $this->price),
            'count' => $this->count
        ];
    }
}
