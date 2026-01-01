<?php

namespace Iyzico\IyzipayLaravel\DTOs;

use Iyzipay\Model\CardInformation;

readonly class CardData
{
    public function __construct(
        public string $holderName,
        public string $cardNumber,
        public string $expireMonth,
        public string $expireYear,
        public ?string $alias = null
    ) {}

    /**
     * Create instance from the legacy array structure.
     * Preserves the old mapping: 'holder' -> holderName, etc.
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            holderName: $attributes['holder'],
            cardNumber: $attributes['number'],
            expireMonth: $attributes['month'],
            expireYear: $attributes['year'],
            alias: $attributes['alias'] ?? null
        );
    }

    /**
     * Convert this DTO into the official Iyzico SDK Model.
     */
    public function toIyzipayModel(): CardInformation
    {
        $card = new CardInformation();
        $card->setCardHolderName($this->holderName);
        $card->setCardNumber($this->cardNumber);
        $card->setExpireMonth($this->expireMonth);
        $card->setExpireYear($this->expireYear);

        if ($this->alias) {
            $card->setCardAlias($this->alias);
        }

        return $card;
    }
}
