<?php

namespace Iyzico\IyzipayLaravel\Traits;

use Iyzico\IyzipayLaravel\DTOs\CardData;
use Iyzico\IyzipayLaravel\Exceptions\Card\CardRemoveException;
use Iyzico\IyzipayLaravel\Exceptions\Card\CardSaveException;
use Iyzico\IyzipayLaravel\Exceptions\Fields\CreditCardFieldsException;
use Iyzico\IyzipayLaravel\Models\CreditCard;
use Iyzico\IyzipayLaravel\PayableContract as Payable;
use Illuminate\Support\Facades\Validator;
use Iyzipay\Model\Card;
use Iyzipay\Model\CardInformation;
use Iyzipay\Options;
use Iyzipay\Request\CreateCardRequest;
use Iyzipay\Request\DeleteCardRequest;

trait PreparesCreditCardRequest
{

    /**
     * @param CardData|array $attributes
     * @throws CreditCardFieldsException
     */
    private function validateCreditCardAttributes(CardData|array $attributes): void
    {
        // keep backwards compatibility with old array structure
        if ($attributes instanceof CardData) {
            $data = $attributes->toArray();
        } else {
            $data = $attributes;
        }

        $v = Validator::make($data, [
            'alias'  => 'required',
            'holder' => 'required',
            'number' => 'required|digits_between:15,16',
            'month'  => 'required|digits:2',
            'year'   => 'required|digits:4'
        ]);

        if ($v->fails()) {
            throw new CreditCardFieldsException(implode(',', $v->errors()->all()));
        }
    }

    /**
     * Prepares credit card on iyzipay.
     *
     * @param Payable $payable
     * @param CardData|array $attributes
     * @return Card
     * @throws CardSaveException
     */
    private function createCardOnIyzipay(Payable $payable, CardData|array $attributes): Card
    {
        $cardRequest = $this->createCardRequest($payable, $attributes);

        try {
            $card = Card::create($cardRequest, $this->getOptions());
        } catch (\Exception $e) {
            throw new CardSaveException();
        }

        unset($cardRequest);

        if ($card->getStatus() != 'success') {
            throw new CardSaveException($card->getErrorMessage());
        }
        return $card;
    }

    /**
     * Prepare card request class for iyzipay.
     *
     * @param Payable $payable
     * @param CardData|array $attributes
     * @return CreateCardRequest
     */
    private function createCardRequest(Payable $payable, CardData|array $attributes): CreateCardRequest
    {
        $cardRequest = new CreateCardRequest();
        $cardRequest->setLocale($this->getLocale());
        $cardRequest->setEmail($payable->bill_fields->email);

        if (!empty($payable->iyzipay_key)) {
            $cardRequest->setCardUserKey($payable->iyzipay_key);
        }

        $cardRequest->setCard($this->createCardInformation($attributes));

        return $cardRequest;
    }

    /**
     * Removes a card on iyzipay
     *
     * @param CreditCard $creditCard
     * @throws CardRemoveException
     */
    private function removeCardOnIyzipay(CreditCard $creditCard): void
    {
        try {
            $result = Card::delete($this->removeCardRequest($creditCard), $this->getOptions());
        } catch (\Exception $e) {
            throw new CardRemoveException();
        }

        if ($result->getStatus() != 'success') {
            throw new CardRemoveException($result->getErrorMessage());
        }
    }

    /**
     * Prepares remove card request class for iyzipay.
     *
     * @param CreditCard $creditCard
     * @return DeleteCardRequest
     */
    private function removeCardRequest(CreditCard $creditCard): DeleteCardRequest
    {
        $removeRequest = new DeleteCardRequest();
        $removeRequest->setCardUserKey($creditCard->owner->iyzipay_key);
        $removeRequest->setCardToken($creditCard->token);
        $removeRequest->setLocale($this->getLocale());

        return $removeRequest;
    }

    /**
     * Prepares card information class for iyzipay.
     *
     * @param CardData|array $attributes
     * @return CardInformation
     */
    private function createCardInformation(CardData|array $attributes): CardInformation
    {
        //keeps backward compatibility with old array input
        if ($attributes instanceof CardData) {
            return $attributes->toIyzipayModel();
        }
        return CardData::fromArray($attributes)->toIyzipayModel();
    }
    abstract protected function getLocale(): string;

    abstract protected function getOptions(): Options;
}
