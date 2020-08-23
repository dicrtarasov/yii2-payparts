<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 18:08:12
 */

declare(strict_types = 1);
namespace dicr\payparts;

/**
 * Константы PayParts
 */
interface PayParts
{
    /** @var string адрес API */
    public const API_URL = 'https://payparts2.privatbank.ua/ipp/v2';

    /** @var string URL для генерации QrCode */
    public const QR_URL = 'https://payparts2.privatbank.ua/ipp/qr/generate';

    /** @var string Мгновенная рассрочка */
    public const MERCHANT_TYPE_II = 'II';

    /** @var string Мгновенная рассрочка. Акционная. */
    public const MERCHANT_TYPE_IA = 'IA';

    /** @var string Оплата частями */
    public const MERCHANT_TYPE_PP = 'PP';

    /** @var string Деньги в периоде */
    public const MERCHANT_TYPE_PB = 'PB';

    /** @var string типы рассрочек */
    public const MERCHANT_TYPES = [
        self::MERCHANT_TYPE_II => 'Мгновенная рассрочка',
        self::MERCHANT_TYPE_IA => 'Мгновенная рассрочка (акционная)',
        self::MERCHANT_TYPE_PP => 'Оплата частями',
        self::MERCHANT_TYPE_PB => 'Оплата частями (деньги в периоде)'
    ];

    /** @var int минимальная сумма */
    public const AMOUNT_MIN = 300;

    /** @var int максимальная сумма */
    public const AMOUNT_MAX = 50000;

    /** @var int минимальное кол-во частей */
    public const PARTS_COUNT_MIN = 2;

    /** @var int максимальное кол-во частей */
    public const PARTS_COUNT_MAX = 25;

    /** @var string платеж создан */
    public const STATE_CREATED = 'CREATED';

    /** @var string отменен клиентов */
    public const STATE_CANCELED = 'CANCELED';

    /** @var string платеж прошел */
    public const STATE_SUCCESS = 'SUCCESS';

    /** @var string ошибка при создании платежа */
    public const STATE_FAIL = 'FAIL';

    /** @var string ожидание оплаты клиентом */
    public const STATE_CLIENT_WAIT = 'CLIENT_WAIT';

    /** @var string подтверждение клиентом пароля */
    public const STATE_OTP_WAITING = 'OTP_WAITING';

    /** @var string создание контракта для платежа */
    public const STATE_PP_CREATION = 'PP_CREATION';

    /** @var string деньги зарезервированы */
    public const STATE_LOCKED = 'LOCKED';

    /** @var string[] статусы платежей */
    public const STATES = [
        self::STATE_CREATED => 'платеж создан',
        self::STATE_CANCELED => 'платеж отменен клиентом',
        self::STATE_SUCCESS => 'платеж совершен',
        self::STATE_FAIL => 'ошибка создания платежа',
        self::STATE_CLIENT_WAIT => 'ожидание оплаты клиентом',
        self::STATE_OTP_WAITING => 'подтверждение клиентом ОТП-пароля',
        self::STATE_PP_CREATION => 'создание контракта платежа',
        self::STATE_LOCKED => 'ожидает подтверждение магазином'
    ];

    /** @var string тестовый storeId */
    public const TEST_STORE_ID = '4AAD1369CF734B64B70F';

    /** @var string тестовый пароль */
    public const TEST_PASSWORD = '75bef16bfdce4d0e9c0ad5a19b9940df';
}
