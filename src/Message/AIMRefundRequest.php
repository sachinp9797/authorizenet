<?php

namespace Omnipay\AuthorizeNet\Message;

use Omnipay\AuthorizeNet\Message\Query\QueryDetailRequest;
use Omnipay\AuthorizeNet\Message\Query\QueryDetailResponse;
use stdClass;

/**
 * Authorize.net AIM Refund Request
 */
class AIMRefundRequest extends AIMAbstractRequest
{
    const ERROR_CODE_INVALID_PAYMENT = "E00051";

    protected $action = 'refundTransaction';

    public function shouldVoidIfRefundFails()
    {
        return !!$this->getParameter('voidIfRefundFails');
    }

    public function setVoidIfRefundFails($value)
    {
        $this->setParameter('voidIfRefundFails', $value);
    }

    public function getData()
    {
        $this->validate('transactionReference', 'amount');

        $data = $this->getBaseData();
        $data->transactionRequest->amount = $this->getParameter('amount');

        $transactionRef = $this->getTransactionReference();
        if ($card = $transactionRef->getCard()) {
            $data->transactionRequest->payment->creditCard->cardNumber = $card->number;
            $data->transactionRequest->payment->creditCard->expirationDate = $card->expiry;
        } elseif ($cardRef = $transactionRef->getCardReference()) {
            $data->transactionRequest->profile->customerProfileId = $cardRef->getCustomerProfileId();
            $data->transactionRequest->profile->paymentProfile->paymentProfileId = $cardRef->getPaymentProfileId();
        } else {
            // Transaction reference only contains the transaction ID, so a card is required
            $this->validate('card');
            $card = $this->getCard();
            $data->transactionRequest->payment->creditCard->cardNumber = $card->getNumberLastFour();
            if ($card->getExpiryMonth()) {
                $data->transactionRequest->payment->creditCard->expirationDate = $card->getExpiryDate('my');
            }
        }
        $data->transactionRequest->refTransId = $transactionRef->getTransId();

        $this->addTransactionSettings($data);

        return $data;
    }

    public function send()
    {
        /** @var AIMResponse $response */
        $response = parent::send();

        if (!$response->isSuccessful()) {
            $shouldVoid = $this->shouldVoidIfRefundFails();
            $isCreditIssueError = $response->getReasonCode() == AIMResponse::ERROR_RESPONSE_CODE_CANNOT_ISSUE_CREDIT;
            if ($shouldVoid && $isCreditIssueError) {
                // This transaction has not yet been settled, hence cannot be refunded. But a void is possible.
                $voidRequest = new CIMVoidRequest($this->httpClient, $this->httpRequest);
                $voidRequest->initialize($this->getParameters());
                $response = $voidRequest->send();
            } else {
                $errorCode = null;
                if ($response->getData() && isset($response->getData()->messages->message)) {
                    $errorCode = (string) $response->getData()->messages->message->code;
                }

                if ($errorCode === self::ERROR_CODE_INVALID_PAYMENT) {
                    /** @var QueryDetailResponse $transactionResponse */
                    $transactionResponse = $this->getTransactionDetails();

                    if (!$transactionResponse->isSuccessful()) {
                        return $response;
                    }

                    $transactionData = $transactionResponse->getTransaction();
                    $hasCustomerProfileId = isset($transactionData['profile']['customerProfileId']);
                    $hasCreditCardInfo = isset($transactionData['payment']['creditCard']);

                    $providedCard = $this->getParameters()['card'] ?? null;

                    if ($hasCreditCardInfo && !$hasCustomerProfileId && $providedCard && isset($providedCard->getParameters()['number'])) {
                        $transactionCardInfo = $transactionData['payment']['creditCard'];
                        $providedCardNumber = $providedCard->getParameters()['number'];
                        // Last 4 digits from the provided full card number
                        $providedCardLast4 = substr($providedCardNumber, -4);
                        // Last 4 digits from the transaction's recorded card number
                        $transactionCardLast4 = substr($transactionCardInfo['cardNumber'], -4);

                        // Proceed with the refund only if the last 4 digits match
                        if ($providedCardLast4 === $transactionCardLast4) {
                            // Create a card object to attach card details to the transaction reference
                            $cardDetails = new stdClass();
                            $cardDetails->number = $transactionCardInfo['cardNumber'];
                            $cardDetails->expiry = $transactionCardInfo['expirationDate'];
                            $this->getTransactionReference()->setCard($cardDetails);

                            $response = parent::send();
                        }
                    }
                }

            }
        }

        return $response;
    }


    /**
     * Helper to get transaction details for the given transaction reference.
     */
    private function getTransactionDetails()
    {
        $transId = $this->getTransactionReference()->getTransId();
        $queryRequest = new QueryDetailRequest($this->httpClient, $this->httpRequest);
        $parameters = array_replace($this->getParameters(), array('transactionReference' => $transId));
        $queryRequest->initialize(array_replace($this->getParameters(), $parameters));
        return $queryRequest->send();
    }
}
