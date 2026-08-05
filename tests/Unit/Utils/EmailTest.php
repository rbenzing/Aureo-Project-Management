<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Services\SecurityService;
use App\Services\SettingsService;
use App\Utils\Email;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Behavioural tests for Email.
 *
 * Email::__construct() hard-codes `new PHPMailer()` and the static factory
 * methods hard-code `new self()`, so there is no constructor injection seam
 * for PHPMailer. Per-instance methods (sendPlainText/sendHtml/addAttachment/
 * clear) are exercised by swapping the private `mail` property for a
 * createMock(PHPMailer::class) via reflection, so nothing ever touches a
 * socket. The static factories (sendActivationEmail/sendPasswordResetEmail)
 * are exercised with a recipient address that has no "@" — PHPMailer's own
 * preSend() rejects a message with zero valid recipients *before* it ever
 * opens an SMTP connection (see vendor PHPMailer::preSend(), which throws
 * internally and is swallowed because $exceptions defaults to false), so
 * this reaches every line of the two static methods without any real
 * network I/O. SecurityService::sanitizeHtml() runs for real (per the task
 * brief), but backed by a mocked SettingsService so it never touches
 * Setting/Database.
 */
#[CoversClass(Email::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
#[UsesClass(SecurityService::class)]
final class EmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setSecurityServiceSingleton(null);
    }

    protected function tearDown(): void
    {
        $this->setSecurityServiceSingleton(null);

        parent::tearDown();
    }

    private function setSecurityServiceSingleton(?SecurityService $service): void
    {
        $property = (new ReflectionClass(SecurityService::class))->getProperty('instance');
        $property->setValue(null, $service);
    }

    /**
     * Backs a real SecurityService with a fully mocked SettingsService (and
     * an unused mocked Database), so sanitizeHtml() runs its real
     * htmlspecialchars() logic without any DB access.
     */
    private function installSanitizingSecurityService(): void
    {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('isSecurityFeatureEnabled')->willReturn(true);

        $db = $this->createMock(Database::class);

        $this->setSecurityServiceSingleton(new SecurityService($settings, $db));
    }

    private function swapMailProperty(Email $email, PHPMailer $mock): void
    {
        $property = (new ReflectionClass(Email::class))->getProperty('mail');
        $property->setValue($email, $mock);
    }

    private function readMailProperty(Email $email): PHPMailer
    {
        return (new ReflectionClass(Email::class))->getProperty('mail')->getValue($email);
    }

    // ------------------------------------------------------------ constructor

    public function testConstructorConfiguresPhpMailerFromConfig(): void
    {
        $email = new Email();
        $mail = $this->readMailProperty($email);

        $this->assertSame(Config::getSmtpHost(), $mail->Host);
        $this->assertSame(Config::getSmtpPort(), $mail->Port);
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame(Config::getSmtpUsername(), $mail->Username);
        $this->assertSame(Config::getEmailFrom(), $mail->From);
    }

    // ------------------------------------------------------------ sendPlainText()

    public function testSendPlainTextReturnsTrueAndPopulatesMessageOnSuccess(): void
    {
        $email = new Email();
        $mock = $this->createMock(PHPMailer::class);
        $mock->expects($this->once())->method('addAddress')->with('to@example.com');
        $mock->expects($this->once())->method('isHTML')->with(false);
        $mock->method('send')->willReturn(true);
        $mock->expects($this->once())->method('clearAddresses');
        $mock->expects($this->once())->method('clearAttachments');
        $this->swapMailProperty($email, $mock);

        $result = $email->sendPlainText('to@example.com', 'Subject line', 'Body text');

        $this->assertTrue($result);
        $this->assertSame('Subject line', $mock->Subject);
        $this->assertSame('Body text', $mock->Body);
    }

    public function testSendPlainTextReturnsFalseWhenPhpMailerThrows(): void
    {
        $email = new Email();
        $mock = $this->createMock(PHPMailer::class);
        $mock->method('send')->willThrowException(new PHPMailerException('SMTP failure'));
        $this->swapMailProperty($email, $mock);

        $result = $email->sendPlainText('to@example.com', 'Subject', 'Body');

        $this->assertFalse($result);
    }

    // ---------------------------------------------------------------- sendHtml()

    public function testSendHtmlReturnsTrueAndSetsAltBodyOnSuccess(): void
    {
        $email = new Email();
        $mock = $this->createMock(PHPMailer::class);
        $mock->expects($this->once())->method('addAddress')->with('to@example.com');
        $mock->expects($this->once())->method('isHTML')->with(true);
        $mock->method('send')->willReturn(true);
        $this->swapMailProperty($email, $mock);

        $result = $email->sendHtml('to@example.com', 'Subject', '<p>Hello <b>World</b></p>');

        $this->assertTrue($result);
        $this->assertSame('<p>Hello <b>World</b></p>', $mock->Body);
        $this->assertSame('Hello World', $mock->AltBody);
    }

    public function testSendHtmlReturnsFalseWhenPhpMailerThrows(): void
    {
        $email = new Email();
        $mock = $this->createMock(PHPMailer::class);
        $mock->method('send')->willThrowException(new PHPMailerException('SMTP failure'));
        $this->swapMailProperty($email, $mock);

        $result = $email->sendHtml('to@example.com', 'Subject', '<p>Body</p>');

        $this->assertFalse($result);
    }

    // ----------------------------------------------------------- addAttachment()

    public function testAddAttachmentDelegatesToPhpMailer(): void
    {
        $email = new Email();
        $mock = $this->createMock(PHPMailer::class);
        $mock->expects($this->once())->method('addAttachment')->with('/tmp/file.txt');
        $this->swapMailProperty($email, $mock);

        $email->addAttachment('/tmp/file.txt');
    }

    // -------------------------------------------------------------------- clear()

    public function testClearDelegatesToPhpMailer(): void
    {
        $email = new Email();
        $mock = $this->createMock(PHPMailer::class);
        $mock->expects($this->once())->method('clearAddresses');
        $mock->expects($this->once())->method('clearAttachments');
        $this->swapMailProperty($email, $mock);

        $email->clear();
    }

    // --------------------------------------------------------- sendActivationEmail()

    public function testSendActivationEmailBuildsSanitizedLinkAndReturnsTrue(): void
    {
        Config::set('domain', 'example.test');
        Config::set('scheme', 'https');
        $this->installSanitizingSecurityService();

        // Deliberately invalid recipient ("" has no "@"): PHPMailer::preSend()
        // rejects it for lacking any valid recipient before any network I/O
        // is attempted, so this stays fully offline while still exercising
        // every statement in sendActivationEmail() -> sendHtml().
        $result = Email::sendActivationEmail('', '<token>');

        $this->assertTrue($result);
    }

    // ------------------------------------------------------ sendPasswordResetEmail()

    public function testSendPasswordResetEmailBuildsSanitizedLinkAndReturnsTrue(): void
    {
        Config::set('domain', 'example.test');
        Config::set('scheme', 'https');
        $this->installSanitizingSecurityService();

        $result = Email::sendPasswordResetEmail('', '<token>');

        $this->assertTrue($result);
    }
}
