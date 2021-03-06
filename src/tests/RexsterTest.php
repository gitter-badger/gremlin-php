<?php
namespace brightzone\rexpro\tests;

use brightzone\rexpro\Connection;
use brightzone\rexpro\Helper;
use brightzone\rexpro\Messages;

/**
 * Unit testing of grmlin-php
 *
 * @category DB
 * @package  gremlin-php-tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class RexsterTest extends RexsterTestCase
{
    /**
     * Testing UUID
     *
     * @return void
     */
    public function testCreateUuid()
    {
        $uuid1 = Helper::createUuid();
        $this->assertTRUE(mb_strlen($uuid1, 'ISO-8859-1') == 36, 'The generated UUID is not the correct length ');
        $this->assertTRUE(count(str_split($uuid1, 1)) == 36, 'The generated UUID is not the correct length');

        $uuid = Helper::uuidToBin($uuid1);
        $this->assertTRUE(mb_strlen($uuid, 'ISO-8859-1') == 16, 'The conversion to bin of the UUID is not the correct length (16 bytes)');
        $this->assertTRUE(count(str_split($uuid, 1)) == 16, 'The conversion to bin of the UUID is not the correct length (16 bytes)');
        //test that the bin format is correct for rexPro
        $this->assertEquals(bin2hex($uuid), str_replace('-', '', trim($uuid1)), 'The conversion to bin of the UUID is incorrect');

        $uuid = Helper::binToUuid($uuid);
        $this->assertTRUE(mb_strlen($uuid, 'ISO-8859-1') == 36, 'The conversion of bin UUID to UUID is not the correct length');
        $this->assertTRUE(count(str_split($uuid, 1)) == 36, 'The conversion of bin UUID to UUID is not the correct length');
        $this->assertEquals($uuid, $uuid1, 'UUID before and after convertion do not match');
    }

    /**
     * Testing binary conversion TO
     *
     * @return void
     */
    public function testConvertIntTo32Bit()
    {
        $converted = Helper::convertIntTo32Bit(84);
        $this->assertEquals(mb_strlen($converted, 'ISO-8859-1'), 4, 'The converted int is not the correct byte length (4 bytes)'); //should be 32 bits / 4 bytes
        $this->assertEquals(bin2hex($converted), '00000054', 'The converted int is incorrect');

        $converted = Helper::convertIntTo32Bit(9999);
        $this->assertEquals(mb_strlen($converted, 'ISO-8859-1'), 4, 'The converted int is not the correct byte length (4 bytes)'); //should be 32 bits / 4 bytes
        $this->assertEquals(bin2hex($converted), '0000270f', 'The converted int is incorrect');

        $converted = Helper::convertIntTo32Bit(10000000000);
        $this->assertEquals(mb_strlen($converted, 'ISO-8859-1'), 4, 'The converted int is not the correct byte length (4 bytes)'); //should be 32 bits / 4 bytes
        $this->assertNotEquals(bin2hex($converted), '2540BE400', 'The converted int is incorrect. ints above 4 bytes should have the extra bytes truncated'); // hex for 10000000000
        //the extra 3 bits should be taken off the begining of binary data. This test checks this
        $this->assertEquals(bin2hex($converted), '540be400', 'The converted int is incorrect. ints above 4 bytes should have the extra bytes truncated');


    }

    /**
     * Testing binary conversion FROM
     *
     * @return void
     */
    public function testConvertIntFrom32Bit()
    {
        $converted = Helper::convertIntFrom32Bit(Helper::convertIntTo32Bit(84));
        $this->assertEquals($converted, 84, 'The conversion of 32bit int to int is incorrect');

        $converted = Helper::convertIntFrom32Bit(Helper::convertIntTo32Bit(9999));
        $this->assertEquals($converted, 9999, 'The conversion of 32bit int to int is incorrect');

        $converted = Helper::convertIntFrom32Bit(Helper::convertIntTo32Bit(10000000000));
        $this->assertEquals($converted, 1410065408, 'The conversion of 32bit int to int is incorrect. Bit truncating issue'); //bit truncating check
    }

    /**
     * Testing Connection
     *
     * @return void
     */
    public function testConnectSuccess()
    {
        $db = new Connection;
        $db->open('localhost', 'graph', $this->username, $this->password);

        $db = new Connection;
        $db->open('localhost', 'graph', $this->username, $this->password);

        $db = new Connection;
        $db->open('localhost', 'graph', $this->username, $this->password);
    }

    /**
     * Testing unknown host connection errors
     *
     * @expectedException \brightzone\rexpro\InternalException
     *
     * @return void
     */
    public function testConnectErrorsUknownHost()
    {
        $db = new Connection;
        $db->timeout = 0.5;
        $db->open('unknownhost');
    }

    /**
     * Testing wrong port connection errors
     *
     * @expectedException \brightzone\rexpro\InternalException
     *
     * @return void
     */
    public function testConnectErrorsWrongPort()
    {
        $db = new Connection;
        $db->timeout = 0.5;
        $db->open('localhost:8787');
    }

    /**
     * Testing connection close
     *
     * @return void
     */
    public function testConnectCloseSuccess()
    {
        //do all connection checks
        $db = new Connection;
        $db->open('localhost', 'graph', $this->username, $this->password);

        //check disconnection
        $db->close();
    }

    /**
     * Testing Script run against DB
     *
     * @return void
     */
    public function testRunScriptNoSession()
    {
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graph', $this->username, $this->password);
        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $result = $db->send('5+5');
        $this->assertEquals(10, $result[0], 'Script response message is not the right type. (Maybe it\'s an error)');

        $result = $db->send('g.V()');
        $this->assertEquals(6, count($result), 'Script response message is not the right type. (Maybe it\'s an error)');

        //check disconnection
        $db->close();
        $this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is not established');
    }


    /**
     * Testing Script run against DB
     * Sessions and transactions are linked ATM
     *
     * @return void
     */
    public function testRunScriptSession()
    {
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graph', $this->username, $this->password);

        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $result = $db->send('5+5', 'session', 'eval');

        $this->assertEquals($result[0], 10, 'Script response message is not the right type. (Maybe it\'s an error)'); //check it's a session script reply

        $result = $db->send('g.V()', 'session', 'eval');
        $this->assertEquals(count($result), 6, 'Script response message is not the right type. (Maybe it\'s an error)'); //check it's a session script reply

        //check disconnection
        $db->close();
        $this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is not established');
        $this->assertFALSE($db->inTransaction(), 'Despite closing, transaction not closed');
    }

    /**
     * Testing Script run with bindings
     *
     * @return void
     */
    public function testRunScriptWithBindings()
    {
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graph', $this->username, $this->password);
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'g.V(CUSTO_BINDING)';
        $db->message->bindValue('CUSTO_BINDING', 2);
        $result = $db->send();

        $this->assertNotEquals($result, FALSE, 'Running a script with bindings produced an error');

        //check disconnection
        $message = $db->close();
        $this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used created an error');
    }

    /**
     * Testing Script run with bindings
     *
     * @return void
     */
    public function testRunScriptWithVarsInSession()
    {
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graph', $this->username, $this->password);
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'cal = 5+5';
        $db->message->processor = 'session';
        $db->message->setArguments(['session'=>$db->getSession()]);
        $result = $db->send(NULL);

        $this->assertNotEquals($result, FALSE, 'Running a script with bindings produced an error');

        $db->message->gremlin = 'cal = 5+5';
        $result = $db->send(NULL, 'session', 'eval');
        $this->assertEquals($result, [10], 'Running a script with bindings produced an error');


        //check disconnection
        $message = $db->close();
        $this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used created an error');
    }

    /**
     * Testing Script run with bindings
     *
     * @return void
     */
    public function testRunScriptWithBindingsInSession()
    {
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graph', $this->username, $this->password);
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'g.V(CUSTO_BIND)';
        $db->message->bindValue('CUSTO_BIND', 2);
        $result = $db->send(NULL, 'session', 'eval');

        $this->assertNotEquals($result, [], 'Running a script with bindings produced an error');

        $db->message->gremlin = 'g.V(CUSTO_BIND)';
        $result = $db->send(NULL, 'session', 'eval');
        $this->assertNotEquals($result, [], 'Running a script with bindings produced an error');


        //check disconnection
        $message = $db->close();
        $this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used created an error');
    }

    /**
     * Testing sendMessage without previous connection
     *
     * @expectedException \brightzone\rexpro\InternalException
     *
     * @return void
     */
    public function testSendMessageWithoutConnection()
    {
        $db = new Connection;
        $msg = new Messages();
        $db->send($msg);
    }

    /**
     * Testing runScript() without making a previous
     * socket connection with open()
     *
     * @expectedException \brightzone\rexpro\InternalException
     *
     * @return void
     */
    public function testRunScriptWithoutConnection()
    {
        $db = new Connection;
        $db->send();
    }

    /**
     * Testing getSerializer
     *
     * @return void
     */
    public function testgetSerializer()
    {
        $db = new Connection;
        $serializer = $db->message->getSerializer();

        $this->assertTRUE($serializer instanceof \brightzone\rexpro\serializers\Json, 'Initial serializer set failed');
        $db->message->registerSerializer('\brightzone\rexpro\tests\stubs\TestSerializer');
        $this->assertTRUE($db->message->getSerializer() instanceof \brightzone\rexpro\tests\stubs\TestSerializer, 'Failed to change serializer');
    }

    /**
     * Testing getSerializer name
     *
     * @return void
     */
    public function testgetSerializerName()
    {
        $db = new Connection;
        $serializer = $db->message->getSerializer();

        $this->assertEquals('JSON', $serializer->getName(), 'Incorrect serializer name');
    }

    /**
     * Testing getSerializer by mimeType
     *
     * @return void
     */
    public function testgetSerializerByMimeType()
    {
        $db = new Connection;
        $db->message->registerSerializer('\brightzone\rexpro\tests\stubs\TestSerializer');
        $db->message->registerSerializer('\brightzone\rexpro\serializers\Json');
        $serializer = $db->message->getSerializer('application/json');
        $this->assertEquals('JSON', $serializer->getName(), 'Incorrect serializer name');
        $serializer = $db->message->getSerializer('application/test');
        $this->assertEquals('TEST', $serializer->getName(), 'Incorrect serializer name');
    }

    /**
     * Testing getSerializer
     *
     * @expectedException \brightzone\rexpro\ServerException
     *
     * @return void
     */
    public function testIncorrectGremlin()
    {
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graph', $this->username, $this->password);
        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $db->send('g.V().incorect()');
    }

    /**
     * Testing getSerializer
     *
     * @expectedException \brightzone\rexpro\ServerException
     *
     * @return void
     */
    public function testEmptyResult()
    {
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graph', $this->username, $this->password);
        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $db->send('g.V().has("idontexists")');
    }

    /**
     * Testing Helper random string generator with spaces
     *
     * @return void
     */
    public function testRandomGenerator()
    {
        $string = Helper::generateRandomString(10, TRUE, FALSE);
        $this->assertTrue(strlen($string) == 10, "string should contain 10 characters");
        $this->assertTrue(strpos($string, ' ') !== FALSE, "spaces should have been found");
    }

    /**
     * Testing Message isset
     *
     * @return void
     */
    public function testMessageIsset()
    {
        $db = new Connection;
        $db->open('localhost:8182', 'graph', $this->username, $this->password);
        $this->assertTrue(isset($db->message->gremlin), 'gremlin should not be set');
        $db->message->gremlin = "5 + 5";
        $this->assertTrue(isset($db->message->gremlin), 'gremlin should be set');
        $this->assertTrue(isset($db->message->op), 'op should be set');
    }


    /**
     * Testing Message getter error
     *
     * @expectedException \brightzone\rexpro\InternalException
     *
     * @return void
     */
    public function testMessageGetError()
    {
        $db = new Connection;
        $db->open('localhost:8182', 'graph', $this->username, $this->password);
        $this->assertTrue(isset($db->message->gremlin), 'gremlin should not be set');
        $what = $db->message->something;
    }
}