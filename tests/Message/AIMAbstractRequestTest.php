<?php

namespace Message;

use Omnipay\AuthorizeNet\Message\AIMAbstractRequest;
<<<<<<< HEAD
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Tests\TestCase;
=======
use Omnipay\Tests\TestCase;
use Mockery;
>>>>>>> upper_upstream/master

class AIMAbstractRequestTest extends TestCase
{
    /** @var AIMAbstractRequest */
    private $request;

    public function setUp()
    {
<<<<<<< HEAD
        $this->request = $this->getMockForAbstractClass(
            '\Omnipay\AuthorizeNet\Message\AIMAbstractRequest',
            array(
                $this->createMock(ClientInterface::class),
                $this->createMock('\Symfony\Component\HttpFoundation\Request')
            )
        );
=======
        $this->request = Mockery::mock('\Omnipay\AuthorizeNet\Message\AIMAbstractRequest')->makePartial();
        $this->request->initialize();
>>>>>>> upper_upstream/master
    }

    public function testShouldReturnTransactionReference()
    {
        $complexKey = json_encode(array('transId' => 'TRANS_ID', 'cardReference' => 'CARD_REF'));
        $this->request->setTransactionReference($complexKey);
        $this->assertEquals('TRANS_ID', $this->request->getTransactionReference()->getTransId());
    }

    public function testShouldReturnBackwardCompatibleTransactionReference()
    {
        $this->request->setTransactionReference('TRANS_ID');
        $this->assertEquals('TRANS_ID', $this->request->getTransactionReference()->getTransId());
    }
}
