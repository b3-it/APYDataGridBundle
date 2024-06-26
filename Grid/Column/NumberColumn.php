<?php

/*
 * This file is part of the DataGridBundle.
 *
 * (c) Abhoryo <abhoryo@free.fr>
 * (c) Stanislav Turza
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace APY\DataGridBundle\Grid\Column;

use Symfony\Component\Form\Exception\TransformationFailedException;

class NumberColumn implements ColumnInterface
{
    use ColumnAccessTrait;

    protected $column;

    public function __construct(Column $column, $params = null)
    {
        $this->column = $column;
        $this->__initialize((array) $params);
    }

    protected static $styles = [
        'decimal'    => \NumberFormatter::DECIMAL,
        'percent'    => \NumberFormatter::PERCENT,
        'money'      => \NumberFormatter::CURRENCY,
        'currency'   => \NumberFormatter::CURRENCY,
        'duration'   => \NumberFormatter::DURATION,
        'scientific' => \NumberFormatter::SCIENTIFIC,
        'spellout'   => \NumberFormatter::SPELLOUT,
    ];

    protected $style;

    protected $locale;

    protected $precision;

    protected $grouping;

    protected $roundingMode;

    protected $ruleSet;

    protected $currencyCode;

    protected $fractional;

    protected $maxFractionDigits;

    public function __initialize(array $params)
    {
        $this->column->__initialize($params);

        $this->column->setAlign($this->column->getParam('align', Column::ALIGN_RIGHT));
        $this->setStyle($this->column->getParam('style', 'decimal'));
        $this->setLocale($this->column->getParam('locale', \Locale::getDefault()));
        $this->setPrecision($this->column->getParam('precision', null));
        $this->setGrouping($this->column->getParam('grouping', false));
        $this->setRoundingMode($this->column->getParam('roundingMode', \NumberFormatter::ROUND_HALFUP));
        $this->setRuleSet($this->column->getParam('ruleSet'));
        $this->setCurrencyCode($this->column->getParam('currencyCode'));
        $this->setFractional($this->column->getParam('fractional', false));
        $this->setMaxFractionDigits($this->column->getParam('maxFractionDigits', null));
        if ($this->style === \NumberFormatter::DURATION) {
            $this->setLocale('en');
            $this->setRuleSet($this->column->getParam('ruleSet', '%in-numerals')); // or '%with-words'
        }

        $this->column->setOperators($this->column->getParam('operators', [
            Column::OPERATOR_EQ,
            Column::OPERATOR_NEQ,
            Column::OPERATOR_LT,
            Column::OPERATOR_LTE,
            Column::OPERATOR_GT,
            Column::OPERATOR_GTE,
            Column::OPERATOR_BTW,
            Column::OPERATOR_BTWE,
            Column::OPERATOR_ISNULL,
            Column::OPERATOR_ISNOTNULL,
        ]));
        $this->column->setDefaultOperator($this->column->getParam('defaultOperator', Column::OPERATOR_EQ));

        $this->column->setIsQueryValidCallback([$this, "isQueryValid"]);
    }

    public function isQueryValid($query)
    {
        $result = array_filter((array) $query, 'is_numeric');

        return !empty($result);
    }

    public function renderCell($value, $row, $router)
    {
        if (is_callable($this->column->callback)) {
            return call_user_func($this->column->callback, $value, $row, $router);
        }

        return $this->getDisplayedValue($value);
    }

    public function getDisplayedValue($value)
    {
        if ($value !== null && $value !== '') {
            $formatter = new \NumberFormatter($this->locale, $this->style);

            if ($this->precision !== null) {
                $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->precision);
                $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, $this->roundingMode);
            }

            if ($this->ruleSet !== null) {
                $formatter->setTextAttribute(\NumberFormatter::DEFAULT_RULESET, $this->ruleSet);
            }

            if ($this->maxFractionDigits !== null) {
                $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $this->maxFractionDigits);
            }

            $formatter->setAttribute(\NumberFormatter::GROUPING_USED, $this->grouping);

            if ($this->style === \NumberFormatter::PERCENT && !$this->fractional) {
                $value /= 100;
            }

            if ($this->style === \NumberFormatter::CURRENCY) {
                if ($this->currencyCode === null) {
                    $this->currencyCode = $formatter->getTextAttribute(\NumberFormatter::CURRENCY_CODE);
                }

                if (strlen($this->currencyCode) !== 3) {
                    throw new TransformationFailedException('Your locale definition is not complete, you have to define a language and a country. (.e.g en_US, fr_FR)');
                }

                $value = $formatter->formatCurrency($value, $this->currencyCode);
            } else {
                $value = $formatter->format($value);
            }

            if (intl_is_failure($formatter->getErrorCode())) {
                throw new TransformationFailedException($formatter->getErrorMessage());
            }

            if (array_key_exists((string) $value, $this->column->values)) {
                $value = $this->column->values[$value];
            }

            return $value;
        }

        return '';
    }

    public function getFilters($source)
    {
        $parentFilters = $this->column->getFilters($source);

        $filters = [];
        foreach ($parentFilters as $filter) {
            // Transforme in number for ODM
            $val = $filter->getValue();
            if (is_array($val)) {
                $val = reset($val);
            }
            $filters[] = ($filter->getValue() === null) ? $filter : $filter->setValue((int) $val);
        }

        return $filters;
    }

    public function setStyle($style)
    {
        if (!isset(static::$styles[$style])) {
            throw new \InvalidArgumentException(sprintf('Expected parameter of style "%s", "%s" given', implode('", "', array_keys(static::$styles)), $this->style));
        }

        $this->style = static::$styles[$style];

        return $this;
    }

    public function getStyle()
    {
        return $this->style;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setPrecision($precision)
    {
        $this->precision = $precision;

        return $this;
    }

    public function getPrecision()
    {
        return $this->precision;
    }

    public function setGrouping($grouping)
    {
        $this->grouping = $grouping;

        return $this;
    }

    public function getGrouping()
    {
        return $this->grouping;
    }

    public function setRoundingMode($roundingMode)
    {
        $this->roundingMode = $roundingMode;

        return $this;
    }

    public function getRoundingMode()
    {
        return $this->roundingMode;
    }

    public function setRuleSet($ruleSet)
    {
        $this->ruleSet = $ruleSet;

        return $this;
    }

    public function getRuleSet()
    {
        return $this->ruleSet;
    }

    public function setCurrencyCode($currencyCode)
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    public function setFractional($fractional)
    {
        $this->fractional = $fractional;

        return $this;
    }

    public function getFractional()
    {
        return $this->fractional;
    }

    public function setMaxFractionDigits($maxFractionDigits)
    {
        $this->maxFractionDigits = $maxFractionDigits;
    }

    public function getMaxFractionDigits()
    {
        return $this->maxFractionDigits;
    }

    public function getType()
    {
        return 'number';
    }
}
