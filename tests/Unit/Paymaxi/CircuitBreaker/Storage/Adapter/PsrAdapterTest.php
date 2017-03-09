<?php


namespace Tests\Unit\Paymaxi\Component\CircuitBreaker\Adapter;


use Paymaxi\Component\CircuitBreaker\Storage\Adapter\BaseAdapter;
use Paymaxi\Component\CircuitBreaker\Storage\Adapter\PsrCacheAdapter;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class PsrAdapterTest extends \PHPUnit\Framework\TestCase
{
    /** @var BaseAdapter _adapter */
    private $_adapter;

    /** @var  CacheItemPoolInterface */
    private $psrAdapter;

    protected function setUp()
    {
        $this->psrAdapter = new FilesystemAdapter();
        $this->_adapter = new PsrCacheAdapter($this->psrAdapter, 300);
    }

    protected function tearDown()
    {
        $this->psrAdapter->clear();
        $this->_adapter = null;
        $this->psrAdapter = null;

        parent::tearDown();
    }

    public function testSave() {
        $x = "val";
        $this->_adapter->saveStatus('AAA', 'BBB', $x);
        $this->assertEquals("val", $this->_adapter->loadStatus('AAA', 'BBB'));
    }

    public function testSaveEmpty() {
        $x = "";
        $this->_adapter->saveStatus('X', 'BBB', $x);
        $this->assertEquals("", $this->_adapter->loadStatus('X', 'BBB'));
    }

    public function testSaveClear() {
        $x = "valB";
        $this->_adapter->saveStatus('AAA', 'BBB', $x);
        $this->psrAdapter->clear();

        $this->assertEquals("", $this->_adapter->loadStatus('AAA', 'BBB'));
    }

    public function testLoadStatusSimple() {
        $this->assertEquals("", $this->_adapter->loadStatus('AAA', 'bbb'));
        $x = 'abcde';
        $this->_adapter->saveStatus('AAA', 'bbb', $x);
        $this->assertEquals("", $this->_adapter->loadStatus('AAa', 'bbb'));
        $this->assertEquals("", $this->_adapter->loadStatus('AA', 'bbb'));
        $this->assertEquals("", $this->_adapter->loadStatus('AAAA', 'bbb'));
        $this->assertEquals('abcde', $this->_adapter->loadStatus('AAA', 'bbb'));
    }

    public function testLoadStatusEmpty() {
        $this->assertEquals("", $this->_adapter->loadStatus('', 'bbb'));
        $this->assertEquals("", $this->_adapter->loadStatus('', ''));
        $this->assertEquals("", $this->_adapter->loadStatus('BBB', ''));
        $this->assertEquals("", $this->_adapter->loadStatus('AAA', 'bbb'));
        $this->assertEquals("", $this->_adapter->loadStatus('B', 'bbb'));

        $this->_adapter->saveStatus('B', 'bbb', "");
        $this->assertEquals("", $this->_adapter->loadStatus('A', 'bbb'));
        $this->assertEquals("", $this->_adapter->loadStatus('B', 'bbb'));
    }

}