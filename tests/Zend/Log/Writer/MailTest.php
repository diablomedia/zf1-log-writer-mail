<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */


/**
 * @category   Zend
 * @package    Zend_Log
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @group      Zend_Log
 */
class Zend_Log_Writer_MailTest extends PHPUnit\Framework\TestCase
{
    /**
     * Mock Transport for Zend_Mail
     *
     * @var Zend_Mail_Transport_Abstract
     */
    protected $_transport;

    protected function setUp(): void
    {
        $this->_transport = $this->getMockForAbstractClass(
            'Zend_Mail_Transport_Abstract',
            array()
        );
        Zend_Mail::setDefaultTransport($this->_transport);
    }

    protected function tearDown(): void
    {
        Zend_Mail::clearDefaultTransport();
    }

    /**
     * Tests normal logging, but with multiple messages for a level.
     *
     * @return void
     * @doesNotPerformAssertions
     */
    public function testNormalLoggingMultiplePerLevel()
    {
        list(, , $log) = $this->_getSimpleLogger();
        $log->info('an info message');
        $log->info('a second info message');
    }

    /**
     * Tests normal logging without use of Zend_Layout.
     *
     * @return void
     * @doesNotPerformAssertions
     */
    public function testNormalLoggingNoLayout()
    {
        list(, , $log) = $this->_getSimpleLogger();
        $log->info('an info message');
        $log->warn('a warning message');
    }

    /**
     * Tests normal logging with Zend_Layout usage.
     *
     * @return void
     * @doesNotPerformAssertions
     */
    public function testNormalLoggingWithLayout()
    {
        list(, , $log) = $this->_getSimpleLogger(true);
        $log->info('an info message');
        $log->warn('a warning message');
    }

    /**
     * Tests normal logging with Zend_Layout and a custom formatter for it.
     *
     * @return void
     * @doesNotPerformAssertions
     */
    public function testNormalLoggingWithLayoutAndItsFormatter()
    {
        list(, $writer, $log) = $this->_getSimpleLogger(true);

        // Since I'm using Zend_Layout, I should be able to set a formatter
        // for it.
        $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple());

        // Log some messages to cover those cases.
        $log->info('an info message');
        $log->warn('a warning message');
    }

    /**
     * Tests normal logging with use of Zend_Layout, a custom formatter, and
     * subject prepend text.
     *
     * @return void
     */
    public function testNormalLoggingWithLayoutFormatterAndSubjectPrependText()
    {
        list(, $writer, $log) = $this->_getSimpleLogger(true);
        $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple());
        $return = $writer->setSubjectPrependText('foo');

        $this->assertSame($writer, $return);

        // Log some messages to cover those cases.
        $log->info('an info message');
        $log->warn('a warning message');
    }

    /**
     * Tests setting of subject prepend text.
     *
     * @return void
     */
    public function testSetSubjectPrependTextNormal()
    {
        list($mail, $writer, $log) = $this->_getSimpleLogger();

        $return = $writer->setSubjectPrependText('foo');

        // Ensure that fluent interface is present.
        $this->assertSame($writer, $return);
    }

    /**
     * Tests that the subject prepend text can't be set if the Zend_Mail
     * object already has a subject line set.
     *
     * @return void
     */
    public function testSetSubjectPrependTextPreExisting()
    {
        list($mail, $writer, $log) = $this->_getSimpleLogger();

        // Expect a Zend_Log_Exception because the subject prepend text cannot
        // be set of the Zend_Mail object already has a subject line set.
        $this->expectException('Zend_Log_Exception');

        // Set a subject line so the setSubjectPrependText() call triggers an
        // exception.
        $mail->setSubject('a pre-existing subject line');

        $writer->setSubjectPrependText('foo');
    }

    /**
     * Tests basic fluent interface for setting layout formatter.
     *
     * @return void
     */
    public function testSetLayoutFormatter()
    {
        list(, $writer) = $this->_getSimpleLogger(true);
        $return         = $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple());
        $this->assertSame($writer, $return);
    }

    /**
     * Tests that the layout formatter can be set and retrieved.
     *
     * @return void
     */
    public function testGetLayoutFormatter()
    {
        list(, $writer) = $this->_getSimpleLogger(true);
        $formatter      = new Zend_Log_Formatter_Simple();

        // Ensure that fluent interface is present.
        $returnedWriter = $writer->setLayoutFormatter($formatter);
        $this->assertSame($writer, $returnedWriter);

        // Ensure that the getter returns the same formatter.
        $returnedFormatter = $writer->getLayoutFormatter();
        $this->assertSame($formatter, $returnedFormatter);
    }

    /**
     * Tests setting of the layout formatter when Zend_Layout is not being
     * used.
     *
     * @return void
     */
    public function testSetLayoutFormatterWithoutLayout()
    {
        list(, $writer) = $this->_getSimpleLogger();

        // If Zend_Layout is not being used, a formatter cannot be set for it.
        $this->expectException('Zend_Log_Exception');
        $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple());
    }

    /**
     * Tests destruction of the Zend_Log instance when an error message entry
     * is in place, but the mail can't be sent.  Should result in a warning,
     * which we test for here.
     *
     * @return void
     */
    public function testDestructorMailError()
    {
        set_error_handler(static function (int $errno, string $errstr): never {
            throw new \Exception($errstr, $errno);
        }, E_USER_WARNING);

        list($mail, $writer, $log) = $this->_getSimpleLogger(false);

        // Force the send() method to throw the same exception that would be
        // thrown if, say, the SMTP server couldn't be contacted.
        $mail->expects($this->any())
             ->method('send')
             ->will($this->throwException(new Zend_Mail_Transport_Exception()));

        // Log an error message so that there's something to send via email.
        $log->err('a bogus error message to force mail sending');

        $this->expectException(\Exception::class);
        unset($log);

        restore_error_handler();
    }

    /**
     * Tests destruction of the Zend_Log instance when an error message entry
     * is in place, but the layout can't be rendered.  Should result in a
     * notice, which we test for here.
     *
     * @return void
     */
    public function testDestructorLayoutError()
    {
        set_error_handler(static function (int $errno, string $errstr): never {
            throw new \Exception($errstr, $errno);
        }, E_USER_NOTICE);

        list($mail, $writer, $log, $layout) = $this->_getSimpleLogger(true);

        // Force the render() method to throw the same exception that would
        // be thrown if, say, the layout template file couldn't be found.
        $layout->expects($this->any())
               ->method('render')
               ->will($this->throwException(new Zend_View_Exception('bogus message')));

        // Log an error message so that there's something to send via email.
        $log->err('a bogus error message to force mail sending');

        $this->expectException(\Exception::class);
        unset($log);

        restore_error_handler();
    }

    /**
     * @group ZF-8953
     */
    public function testFluentInterface()
    {
        list(, $writer) = $this->_getSimpleLogger(true);
        $instance       = $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple())
                           ->setSubjectPrependText('subject');

        $this->assertInstanceOf(Zend_Log_Writer_Mail::class, $instance);
    }

    /**
     * @group ZF-9990
     */
    public function testFactory()
    {
        $config = array(
            'from' => array(
                'email' => 'log@test.framework.zend.com'
            ),
            'to'      => 'admin@domain.com',
            'subject' => '[error] exceptions on my application'
        );

        $writer = Zend_Log_Writer_Mail::factory($config);
        $this->assertInstanceOf(Zend_Log_Writer_Mail::class, $writer);

        $writer->write($this->_getEvent());
        $writer->shutdown();

        $this->assertEquals('admin@domain.com', $this->_transport->recipients);
        $this->assertStringContainsString('an info message', $this->_transport->body);
        $this->assertStringContainsString('From: log@test.framework.zend.com', $this->_transport->header);
        $this->assertStringContainsString('To: admin@domain.com', $this->_transport->header);
        $this->assertStringContainsString('Subject: [error] exceptions on my application', $this->_transport->header);
    }

    /**
     * @group ZF-9990
     */
    public function testFactoryShouldSetSubjectPrependText()
    {
        $config = array(
            'subjectPrependText' => '[error] exceptions on my application'
        );

        $writer = Zend_Log_Writer_Mail::factory($config);
        $writer->write($this->_getEvent());
        $writer->shutdown();

        $this->assertStringContainsString('Subject: [error] exceptions on my application (INFO=1)', $this->_transport->header);
    }

    /**
     * @group ZF-9990
     */
    public function testFactoryShouldAcceptCustomMailClass()
    {
        $this->getMockBuilder('Zend_Mail')
            ->setMockClassName('Zend_Stub_Mail_Custom')
            ->getMock();
        $config = array(
            'class' => 'Zend_Stub_Mail_Custom'
        );

        $writer = Zend_Log_Writer_Mail::factory($config);
        $this->assertInstanceOf(Zend_Log_Writer_Mail::class, $writer);
    }

    /**
     * @group ZF-9990
     */
    public function testFactoryShouldSetCharsetForMail()
    {
        $config = array(
            'charset' => 'UTF-8'
        );

        $writer = Zend_Log_Writer_Mail::factory($config);
        $writer->write($this->_getEvent());
        $writer->shutdown();

        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $this->_transport->header);
    }

    /**
     * @group ZF-9990
     */
    public function testFactoryShouldAllowToSetMultipleRecipientsInArray()
    {
        $config = array(
            'to' => array(
                'John Doe' => 'admin1@domain.com',
                'admin2@domain.com'
            ),
            'cc' => array(
                'bug@domain.com',
                'project' => 'projectname@domain.com'
            )
        );

        $writer = Zend_Log_Writer_Mail::factory($config);
        $writer->write($this->_getEvent());
        $writer->shutdown();

        $this->assertStringContainsString('admin1@domain.com', $this->_transport->recipients);
        $this->assertStringContainsString('admin2@domain.com', $this->_transport->recipients);
        $this->assertStringContainsString('bug@domain.com', $this->_transport->recipients);
        $this->assertStringContainsString('projectname@domain.com', $this->_transport->recipients);
        $this->assertStringContainsString('To: John Doe <admin1@domain.com>', $this->_transport->header);
        $this->assertStringContainsString('admin2@domain.com', $this->_transport->header);
        $this->assertStringContainsString('Cc: bug@domain.com', $this->_transport->header);
        $this->assertStringContainsString('project <projectname@domain.com>', $this->_transport->header);
    }

    /**
     * @group ZF-9990
     */
    public function testFactoryWithLayout()
    {
        $config = array(
            'layoutOptions' => array(
                'layoutPath' => dirname(__FILE__) . '/_files'
            )
        );

        $writer = Zend_Log_Writer_Mail::factory($config);
        $writer->write($this->_getEvent());
        $writer->shutdown();

        $this->assertNotEmpty($this->_transport->boundary);
        $this->assertStringContainsString('Content-Type: multipart/', $this->_transport->header);
        $this->assertStringContainsString('boundary=', $this->_transport->header);
        $this->assertStringContainsString('Content-Type: text/plain', $this->_transport->body);
        $this->assertStringContainsString('Content-Type: text/html', $this->_transport->body);
        $this->assertStringContainsString($this->_transport->boundary, $this->_transport->body);
        $this->assertEquals(2, substr_count($this->_transport->body, 'an info message'));
    }

    /**
     * @group ZF-9990
     */
    public function testFactoryShouldSetLayoutFormatter()
    {
        $config = array(
            'layoutOptions' => array(
                'layoutPath' => '/path/to/layout/scripts'
            ),
            'layoutFormatter' => 'Zend_Log_Formatter_Simple'
        );

        $writer = Zend_Log_Writer_Mail::factory($config);
        $this->assertTrue($writer->getLayoutFormatter() instanceof Zend_Log_Formatter_Simple);
    }

    /**
     * @group ZF-9990
     */
    public function testFactoryWithCustomLayoutClass()
    {
        $this->getMockBuilder('Zend_Layout')
            ->setMockClassName('Zend_Stub_Layout_Custom')
            ->getMock();
        $config = array(
            'layout' => 'Zend_Stub_Layout_Custom'
        );

        $writer = Zend_Log_Writer_Mail::factory($config);
        $this->assertInstanceOf(Zend_Log_Writer_Mail::class, $writer);
    }

    /**
     * Returns an array of the Zend_Mail mock object, Zend_Log_Writer_Mail
     * object, and Zend_Log objects.
     *
     * This is just a helper function for the various test methods above.
     *
     * @return array Numerically indexed array of Zend_Mail,
     *               Zend_Log_Writer_Mail, Zend_Log, and Zend_Layout objects,
     *               in that order.
     */
    protected function _getSimpleLogger($useLayout = false)
    {
        // Get a mock object for Zend_Mail so that no emails are actually
        // sent.
        $mail = $this->getMockBuilder('Zend_Mail')
            ->onlyMethods(array('send'))
            ->getMock();

        // The send() method can be called any number of times.
        $mail->expects($this->any())
             ->method('send');

        $mail->addTo('zend_log_writer_mail_test@example.org');
        $mail->setFrom('zend_log_writer_mail_test@example.org');

        // Setup a mock object for Zend_Layout because we can't rely on any
        // layout files being in place.
        if ($useLayout) {
            $layout = $this->getMockBuilder('Zend_Layout')
                ->onlyMethods(array('render'))
                ->getMock();
            $writer = new Zend_Log_Writer_Mail($mail, $layout);
        } else {
            $writer = new Zend_Log_Writer_Mail($mail);
            $layout = null;
        }

        $log = new Zend_Log();
        $log->addWriter($writer);

        return array($mail, $writer, $log, $layout);
    }

    /**
     * Returns a sample of an event
     *
     * @return array
     */
    protected function _getEvent()
    {
        return array(
            'timestamp'    => date('c'),
            'message'      => 'an info message',
            'priority'     => 6,
            'priorityName' => 'INFO'
        );
    }
}
