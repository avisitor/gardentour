<?php

declare(strict_types=1);

/**
 * Bootstrap file for gardentour tests.
 *
 * Sets safety flags BEFORE loading the app/vendor autoloader so that any
 * mail-sending code path reachable from tests (directly or indirectly via
 * the shared avisitor/mailprovider package's MailRuntimeFlags::detect())
 * is guaranteed to skip real delivery. Mirrors the pattern used by
 * outings/tests/bootstrap.php and retree-hawaii's test suite.
 */

// Mark this as a PHPUnit run. mailprovider's MailRuntimeFlags::detect()
// only skips real delivery when BOTH this constant AND MAIL_TEST_MODE are
// truthy - defense in depth so production code accidentally invoked
// outside of tests does not silently swallow mail.
if (!defined('PHPUNIT_TESTING')) {
    define('PHPUNIT_TESTING', true);
}

// Force mail-test-mode/e2e-test-mode on for the whole suite so any current
// or future test that exercises gardentour's sendEmail()/mail_provider_send_mail()
// path never attempts a real SMTP/SES delivery (and never bounces to a
// real or example recipient address).
$_ENV['MAIL_TEST_MODE'] = 'true';
putenv('MAIL_TEST_MODE=true');
$_ENV['E2E_TEST_MODE'] = 'true';
putenv('E2E_TEST_MODE=true');

require_once __DIR__ . '/../vendor/autoload.php';
