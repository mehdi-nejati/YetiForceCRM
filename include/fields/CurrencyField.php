<?php
/* +**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com
 * ********************************************************************************** */

class CurrencyField
{
	private $CURRENCY_PATTERN_PLAIN = '123456789';
	private $CURRENCY_PATTERN_SINGLE_GROUPING = '123456,789';
	private $CURRENCY_PATTERN_THOUSAND_GROUPING = '123,456,789';
	private $CURRENCY_PATTERN_MIXED_GROUPING = '12,34,56,789';

	/**
	 * Currency Format(3,3,3) or (2,2,3).
	 *
	 * @var string
	 */
	public $currencyFormat = '123,456,789';

	/**
	 * Currency Separator for example (comma, dot, hash).
	 *
	 * @var string
	 */
	public $currencySeparator = ',';

	/**
	 * Decimal Separator for example (dot, comma, space).
	 *
	 * @var <type>
	 */
	public $decimalSeparator = '.';

	/**
	 * Number of Decimal Numbers.
	 *
	 * @var int
	 */
	public $numberOfDecimal = 3;

	/**
	 * Currency Id.
	 *
	 * @var int
	 */
	public $currencyId = 1;

	/**
	 * Currency Symbol.
	 *
	 * @var string
	 */
	public $currencySymbol;

	/**
	 * Currency Symbol Placement.
	 */
	public $currencySymbolPlacement;

	/**
	 * Currency Conversion Rate.
	 *
	 * @var Number
	 */
	public $conversionRate = 1;

	/**
	 * Value to be converted.
	 *
	 * @param Number $value
	 */
	public $value;

	/**
	 * Maximum Number Of Currency Decimals.
	 *
	 * @var Number
	 */
	public $maxNumberOfDecimals = 5;

	/**
	 * Constructor.
	 *
	 * @param Number $value
	 */
	public function __construct($value)
	{
		$this->value = $value;
	}

	/**
	 * Initializes the User's Currency Details.
	 *
	 * @param \App\User $user
	 */
	public function initialize($user = null)
	{
		$defaultCharset = AppConfig::main('default_charset');
		if (empty($user)) {
			$user = \App\User::getCurrentUserModel();
		}
		if (!empty($user->getDetail('currency_grouping_pattern'))) {
			$this->currencyFormat = html_entity_decode($user->getDetail('currency_grouping_pattern'), ENT_QUOTES, $defaultCharset);
			$this->currencySeparator = str_replace("\xC2\xA0", ' ', html_entity_decode($user->getDetail('currency_grouping_separator'), ENT_QUOTES, $defaultCharset));
			$this->decimalSeparator = str_replace("\xC2\xA0", ' ', html_entity_decode($user->getDetail('currency_decimal_separator'), ENT_QUOTES, $defaultCharset));
		}
		if (!empty($user->getDetail('currency_id'))) {
			$this->currencyId = $user->getDetail('currency_id');
		} else {
			$this->currencyId = self::getDBCurrencyId();
		}
		$currencyRateAndSymbol = \vtlib\Functions::getCurrencySymbolandRate($this->currencyId);
		$this->currencySymbol = $currencyRateAndSymbol['symbol'];
		$this->conversionRate = $currencyRateAndSymbol['rate'];
		$this->currencySymbolPlacement = $user->getDetail('currency_symbol_placement');
		$this->numberOfDecimal = empty($user->getDetail('no_of_currency_decimals')) ? 2 : (int) $user->getDetail('no_of_currency_decimals');
	}

	public function getCurrencySymbol()
	{
		return $this->currencySymbol;
	}

	/**
	 * Returns the Formatted Currency value for the User.
	 *
	 * @global Users    $current_user
	 *
	 * @param \App\User $user
	 * @param bool      $skipConversion
	 *
	 * @return string - Formatted Currency
	 */
	public static function convertToUserFormat($value, $user = null, $skipConversion = false, $skipFormatting = false)
	{
		// To support negative values
		$negative = false;
		if (stripos($value, '-') === 0) {
			$negative = true;
			$value = substr($value, 1);
		}
		$self = new self($value);
		$value = $self->getDisplayValue($user, $skipConversion, $skipFormatting);
		return ($negative) ? '-' . $value : $value;
	}

	public static function convertToUserFormatSymbol($value, $skipConversion = false, $currencySymbol = false, $skipFormatting = false)
	{
		// To support negative values
		$negative = false;
		if (stripos($value, '-') === 0) {
			$negative = true;
			$value = substr($value, 1);
		}
		$self = new self($value);
		$formattedValue = $self->getDisplayValue(null, $skipConversion, $skipFormatting);
		if ($currencySymbol === false) {
			$currencySymbol = $self->currencySymbol;
		}
		$value = self::appendCurrencySymbol($formattedValue, $currencySymbol, $self->currencySymbolPlacement);
		return ($negative) ? '-' . $value : $value;
	}

	/**
	 * Function that converts the Number into Users Currency.
	 *
	 * @param \App\User $user
	 * @param bool      $skipConversion
	 *
	 * @return Formatted Currency
	 */
	public function getDisplayValue($user = null, $skipConversion = false, $skipFormatting = false)
	{
		if (empty($user)) {
			$user = \App\User::getCurrentUserModel();
		}
		$this->initialize($user);
		$value = $this->value;
		if (empty($value)) {
			$value = 0;
		}
		if ($skipConversion === false) {
			$value = self::convertFromDollar($value, $this->conversionRate);
		}
		if ($skipFormatting === false) {
			$value = $this->formatCurrencyValue($value);
		}
		return $this->currencyDecimalFormat($value, $user);
	}

	/**
	 * Function that converts the Number into Users Currency along with currency symbol.
	 *
	 * @param \App\User $user
	 * @param bool      $skipConversion
	 *
	 * @return Formatted Currency
	 */
	public function getDisplayValueWithSymbol($user = null, $skipConversion = false)
	{
		$formattedValue = $this->getDisplayValue($user, $skipConversion);
		return self::appendCurrencySymbol($formattedValue, $this->currencySymbol, $this->currencySymbolPlacement);
	}

	/**
	 * Static Function that appends the currency symbol to a given currency value, based on the preferred symbol placement.
	 *
	 * @param Number $currencyValue
	 * @param string $currencySymbol
	 * @param string $currencySymbolPlacement
	 *
	 * @return Currency value appended with the currency symbol
	 */
	public static function appendCurrencySymbol($currencyValue, $currencySymbol, $currencySymbolPlacement = '')
	{
		if (!$currencySymbolPlacement) {
			$currencySymbolPlacement = \App\User::getCurrentUserModel()->getDetail('currency_symbol_placement');
		}
		switch ($currencySymbolPlacement) {
			case '1.0$':
				$returnValue = $currencyValue . ' ' . $currencySymbol;
				break;
			case '$1.0':
			default:
				$returnValue = $currencySymbol . ' ' . $currencyValue;
		}
		return $returnValue;
	}

	/**
	 * Function that formats the Number based on the User configured Pattern, Currency separator and Decimal separator.
	 *
	 * @param Number $value
	 *
	 * @return Formatted Currency
	 */
	private function formatCurrencyValue($value)
	{
		$currencyPattern = $this->currencyFormat;
		$currencySeparator = $this->currencySeparator;
		$decimalSeparator = $this->decimalSeparator;
		$currencyDecimalPlaces = $this->numberOfDecimal;
		$value = number_format($value, $currencyDecimalPlaces, '.', '');
		if (empty($currencySeparator)) {
			$currencySeparator = ' ';
		}
		if (empty($decimalSeparator)) {
			$decimalSeparator = ' ';
		}

		if ($value < 0) {
			$sign = '-';
			$value = substr($value, 1);
		} else {
			$sign = '';
		}

		if ($currencyPattern == $this->CURRENCY_PATTERN_PLAIN) {
			// Replace '.' with Decimal Separator
			$number = str_replace('.', $decimalSeparator, $value);

			return $sign . $number;
		}
		if ($currencyPattern == $this->CURRENCY_PATTERN_SINGLE_GROUPING) {
			// Separate the numeric and decimal parts
			$numericParts = explode('.', $value);
			$wholeNumber = $numericParts[0];
			// First part of the number which remains intact
			if (strlen($wholeNumber) > 3) {
				$wholeNumberFirstPart = substr($wholeNumber, 0, strlen($wholeNumber) - 3);
			}
			// Second Part of the number (last 3 digits) which should be separated from the First part using Currency Separator
			$wholeNumberLastPart = substr($wholeNumber, -3);
			// Re-create the whole number with user's configured currency separator
			if (!empty($wholeNumberFirstPart)) {
				$numericParts[0] = $wholeNumberFirstPart . $currencySeparator . $wholeNumberLastPart;
			} else {
				$numericParts[0] = $wholeNumberLastPart;
			}
			// Re-create the currency value combining the whole number and the decimal part using Decimal separator
			$number = implode($decimalSeparator, $numericParts);

			return $sign . $number;
		}
		if ($currencyPattern == $this->CURRENCY_PATTERN_THOUSAND_GROUPING) {
			$negativeNumber = false;
			if ($value < 0) {
				$negativeNumber = true;
			}

			// Separate the numeric and decimal parts
			$numericParts = explode('.', $value);
			$wholeNumber = $numericParts[0];

			//check the whole number is negative value, then separate the negative symbol from whole number
			if ($wholeNumber < 0 || $negativeNumber) {
				$negativeNumber = true;
				$positiveValues = explode('-', $wholeNumber);
				$wholeNumber = $positiveValues[1];
			}

			// Pad the rest of the length in the number string with Leading 0, to get it to the multiples of 3
			$numberLength = strlen($wholeNumber);
			// First grouping digits length
			$OddGroupLength = $numberLength % 3;
			$gapsToBeFilled = 0;
			if ($OddGroupLength > 0) {
				$gapsToBeFilled = 3 - $OddGroupLength;
			}
			$wholeNumber = str_pad($wholeNumber, $numberLength + $gapsToBeFilled, '0', STR_PAD_LEFT);
			// Split the whole number into chunks of 3 digits
			$wholeNumberParts = str_split($wholeNumber, 3);
			// Re-create the whole number with user's configured currency separator
			$numericParts[0] = $wholeNumber = implode($currencySeparator, $wholeNumberParts);
			if ($wholeNumber != 0) {
				$numericParts[0] = ltrim($wholeNumber, '0');
			} else {
				$numericParts[0] = 0;
			}

			//if its negative number, append-back the negative symbol to the whole number part
			if ($negativeNumber) {
				$numericParts[0] = '-' . $numericParts[0];
			}

			// Re-create the currency value combining the whole number and the decimal part using Decimal separator
			$number = implode($decimalSeparator, $numericParts);

			return $sign . $number;
		}
		if ($currencyPattern == $this->CURRENCY_PATTERN_MIXED_GROUPING) {
			$negativeNumber = false;
			if ($value < 0) {
				$negativeNumber = true;
			}

			// Separate the numeric and decimal parts
			$numericParts = explode('.', $value);
			$wholeNumber = $numericParts[0];

			//check the whole number is negative value, then separate the negative symbol from whole number
			if ($wholeNumber < 0 || $negativeNumber) {
				$negativeNumber = true;
				$positiveValues = explode('-', $wholeNumber);
				$wholeNumber = $positiveValues[1];
			}

			// First part of the number which needs separate division
			if (strlen($wholeNumber) > 3) {
				$wholeNumberFirstPart = substr($wholeNumber, 0, strlen($wholeNumber) - 3);
			}
			// Second Part of the number (last 3 digits) which should be separated from the First part using Currency Separator
			$wholeNumberLastPart = substr($wholeNumber, -3);
			if (!empty($wholeNumberFirstPart)) {
				// Pad the rest of the length in the number string with Leading 0, to get it to the multiples of 2
				$numberLength = strlen($wholeNumberFirstPart);
				// First grouping digits length
				$OddGroupLength = $numberLength % 2;
				$gapsToBeFilled = 0;
				if ($OddGroupLength > 0) {
					$gapsToBeFilled = 2 - $OddGroupLength;
				}
				$wholeNumberFirstPart = str_pad($wholeNumberFirstPart, $numberLength + $gapsToBeFilled, '0', STR_PAD_LEFT);
				// Split the first part of tne number into chunks of 2 digits
				$wholeNumberFirstPartElements = str_split($wholeNumberFirstPart, 2);
				$wholeNumberFirstPart = ltrim(implode($currencySeparator, $wholeNumberFirstPartElements), '0');
				$wholeNumberFirstPart = implode($currencySeparator, $wholeNumberFirstPartElements);
				if ($wholeNumberFirstPart != 0) {
					$wholeNumberFirstPart = ltrim($wholeNumberFirstPart, '0');
				} else {
					$wholeNumberFirstPart = 0;
				}
				// Re-create the whole number with user's configured currency separator
				$numericParts[0] = $wholeNumberFirstPart . $currencySeparator . $wholeNumberLastPart;
			} else {
				$numericParts[0] = $wholeNumberLastPart;
			}

			//if its negative number, append-back the negative symbol to the whole number part
			if ($negativeNumber) {
				$numericParts[0] = '-' . $numericParts[0];
			}

			// Re-create the currency value combining the whole number and the decimal part using Decimal separator
			$number = implode($decimalSeparator, $numericParts);
			return $sign . $number;
		}
		return $value;
	}

	/**
	 * Returns the Currency value without formatting for DB Operations.
	 *
	 * @param \App\User $user
	 * @param bool      $skipConversion
	 *
	 * @return Number
	 */
	public function getDBInsertedValue($user = null, $skipConversion = false)
	{
		if (empty($user)) {
			$user = \App\User::getCurrentUserModel();
		}
		$this->initialize($user);
		$currencySeparator = $this->currencySeparator;
		$decimalSeparator = $this->decimalSeparator;
		if (empty($currencySeparator)) {
			$currencySeparator = ' ';
		}
		if (empty($decimalSeparator)) {
			$decimalSeparator = ' ';
		}
		$value = str_replace($currencySeparator, '', $this->value);
		$value = str_replace($decimalSeparator, '.', $value);
		$value = (float) preg_replace('/[^0-9\.-]/', '', $value);
		if ($skipConversion === false) {
			$value = self::convertToDollar($value, $this->conversionRate);
		}
		return $value;
	}

	/**
	 * Returns the Currency value without formatting for DB Operations.
	 *
	 * @param Number    $value
	 * @param \App\User $user
	 * @param bool      $skipConversion
	 *
	 * @return Number
	 */
	public static function convertToDBFormat($value, $user = null, $skipConversion = false)
	{
		if (empty($value)) {
			return 0;
		}
		$self = new self($value);
		return $self->getDBInsertedValue($user, $skipConversion);
	}

	/**
	 * Function to get the default CRM currency.
	 *
	 * @return int Default system currency id
	 */
	public static function getDBCurrencyId()
	{
		$id = (new \App\Db\Query())->select('id')->from('vtiger_currency_info')->where(['<', 'defaultid', 0])->scalar();
		if ($id) {
			return $id;
		}
		return null;
	}

	public static function convertToDollar($amount, $conversionRate)
	{
		if ($conversionRate == 0) {
			return 0;
		}
		return $amount / $conversionRate;
	}

	public static function convertFromDollar($amount, $conversionRate)
	{
		$currencyField = new self($amount);

		return round($amount * $conversionRate, $currencyField->maxNumberOfDecimals);
	}

	/** This function returns the amount converted from master currency.
	 * param $amount - amount to be converted.
	 * param $crate - conversion rate.
	 */
	public static function convertFromMasterCurrency($amount, $conversionRate)
	{
		return $amount * $conversionRate;
	}

	public function currencyDecimalFormat($value, $user = null)
	{
		if (!$user) {
			$user = \App\User::getCurrentUserModel();
		}
		if ($user->getDetail('truncate_trailing_zeros')) {
			if (strpos($value, $user->getDetail('currency_decimal_separator')) !== 0) {
				/**
				 * We should trim extra zero's if only the value had decimal separator(Ex :- 1600.00)
				 * else it'll change orginal value.
				 */
				$value = rtrim($value, '0');
			}
			if ($user->getDetail('currency_decimal_separator') === '&nbsp;') {
				$decimalSeparator = ' ';
			} else {
				$decimalSeparator = $user->getDetail('currency_decimal_separator');
			}
			$fieldValue = explode(App\Purifier::decodeHtml($decimalSeparator), $value);
			if (strlen($fieldValue[1]) <= 1) {
				if (strlen($fieldValue[1]) == 1) {
					return $value = $fieldValue[0] . $decimalSeparator . $fieldValue[1];
				} elseif (!strlen($fieldValue[1])) {
					return $value = $fieldValue[0];
				} else {
					return $value = $fieldValue[0] . $decimalSeparator;
				}
			} else {
				return preg_replace('/(?<=\\.[0-9])[0]+$/', '', $value);
			}
		} else {
			return $value;
		}
	}
}
