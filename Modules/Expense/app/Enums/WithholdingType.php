<?php

namespace Modules\Expense\Enums;

/**
 * Tipe pemotongan kolom 15 form. Nilainya `nominal`, bukan `amount` seperti
 * di PRD, supaya konsisten dengan PurchasePaymentWithholding di modul Payment.
 */
enum WithholdingType: string
{
    case Percent = 'percent';
    case Nominal = 'nominal';
}
