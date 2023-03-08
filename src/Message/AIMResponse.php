<?php

namespace Omnipay\AuthorizeNet\Message;

use Omnipay\AuthorizeNet\Model\CardReference;
use Omnipay\AuthorizeNet\Model\TransactionReference;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\AbstractResponse;

/**
 * Authorize.Net AIM Response
 */
class AIMResponse extends AbstractResponse
{
    /**
     * For Error codes: @see https://developer.authorize.net/api/reference/responseCodes.html
     */
    const ERROR_RESPONSE_CODE_CANNOT_ISSUE_CREDIT = 54;

    /**
     * The overall transaction result code.
     */
    const TRANSACTION_RESULT_CODE_APPROVED = 1;
    const TRANSACTION_RESULT_CODE_DECLINED = 2;
    const TRANSACTION_RESULT_CODE_ERROR    = 3;
    const TRANSACTION_RESULT_CODE_REVIEW   = 4;

    public function __construct(AbstractRequest $request, $data)
    {
        // Strip out the xmlns junk so that PHP can parse the XML
        $xml = preg_replace('/<createTransactionResponse[^>]+>/', '<createTransactionResponse>', (string)$data);

        try {
            $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOWARNING);
        } catch (\Exception $e) {
            throw new InvalidResponseException();
        }

        if (!$xml) {
            throw new InvalidResponseException();
        }

        parent::__construct($request, $xml);
    }

    public function isSuccessful()
    {
        return static::TRANSACTION_RESULT_CODE_APPROVED === $this->getResultCode();
    }

    /**
     * Status of the transaction. This field is also known as "Response Code" in Authorize.NET terminology.
     * A result of 0 is returned if there is no transaction response returned, e.g. a validation error in
     * some data, or invalid login credentials.
     *
     * @return int 1 = Approved, 2 = Declined, 3 = Error, 4 = Held for Review
     */
    public function getResultCode()
    {
        return intval((string)$this->data->transactionResponse->responseCode);
    }

    /**
     * A more detailed version of the Result/Response code.
     *
     * @return int|null
     */
    public function getReasonCode()
    {
        $code = null;

        if (isset($this->data->transactionResponse->messages)) {
            // In case of a successful transaction, a "messages" element is present
            $code = intval((string)$this->data->transactionResponse->messages->message->code);
        } elseif (isset($this->data->transactionResponse->errors)) {
            // In case of an unsuccessful transaction, an "errors" element is present
            $code = intval((string)$this->data->transactionResponse->errors->error->errorCode);
        }

        return $code;
    }

    /**
     * Text description of the status.
     *
     * @return string|null
     */
    public function getMessage()
    {
        $message = null;

        if (isset($this->data->transactionResponse->messages)) {
            // In case of a successful transaction, a "messages" element is present
            $message = (string)$this->data->transactionResponse->messages->message->description;
        } elseif (isset($this->data->transactionResponse->errors)) {
            // In case of an unsuccessful transaction, an "errors" element is present
            $message = (string)$this->data->transactionResponse->errors->error->errorText;
        }

        return $message;
    }

    public function getAuthorizationCode()
    {
        return (string)$this->data->transactionResponse->authCode;
    }

    /**
     * Returns the Address Verification Service return code.
     *
     * @return string A single character. Can be A, B, E, G, N, P, R, S, U, X, Y, or Z.
     */
    public function getAVSCode()
    {
        return (string)$this->data->transactionResponse->avsResultCode;
    }
    
    /**
     * Returns the Card Code Verfication return code.
     *
     * @return string A single character. Can be M, N, P, S, or U.
     */
    public function getCVVCode()
    {
        if (isset($this->data->transactionResponse[0]->cvvResultCode)) {
            return (string)$this->data->transactionResponse[0]->cvvResultCode;
        } else {
            return '';
        }
    }
    /**
     * A composite key containing the gateway provided transaction reference as
     * well as other data points that may be required for subsequent transactions
     * that may need to modify this one.
     *
     * @param bool $serialize Determines whether a string or object is returned
     * @return TransactionReference|string
     */
    public function getTransactionReference($serialize = true)
    {
        if ($this->isSuccessful()) {
            $body = $this->data->transactionResponse;
            $transactionRef = new TransactionReference();
            $transactionRef->setApprovalCode((string)$body->authCode);
            $transactionRef->setTransId((string)$body->transId);

            try {
                // Need to store card details in the transaction reference since it is required when doing a refund
                if ($card = $this->request->getCard()) {
                    $transactionRef->setCard(array(
                        'number' => $card->getNumberLastFour(),
                        'expiry' => $card->getExpiryDate('mY')
                    ));
                } elseif ($cardReference = $this->request->getCardReference()) {
                    $transactionRef->setCardReference(new CardReference($cardReference));
                }
            } catch (\Exception $e) {
            }

            return $serialize ? (string)$transactionRef : $transactionRef;
        }

        return '';
    }

    /**
     * Returns the account type used for the transaction.
     *
     * @return string A multicharacter string.
     *  Can be Visa, MasterCard, Discover, AmericanExpress, DinersClub, JCB, or eCheck.
     */
    public function getAccountType()
    {
        if (isset($this->data->transactionResponse[0]->accountType)) {
            return (string)$this->data->transactionResponse[0]->accountType;
        } else {
            return '';
        }
    }
}
