<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 23:35:16
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\json\JsonEntity;

/**
 * Данные товара.
 *
 * @property-read float $sum сумма
 */
class Product extends JsonEntity
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
    public function attributeFields() : array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function rules() : array
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
            ['price', 'filter', 'filter' => static function ($val) : float {
                return round((float)$val, 2);
            }]
        ];
    }

    /**
     * Сумма.
     *
     * @return float
     */
    public function getSum() : float
    {
        return $this->count * round((float)$this->price, 2);
    }
}
