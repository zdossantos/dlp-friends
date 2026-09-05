<?php

namespace Tests\Feature\Mail;

use App\Mail\MemberDeletedByAdminMail;
use Tests\TestCase;

class MemberDeletedByAdminMailTest extends TestCase
{
    public function test_mail_is_rendered_in_french(): void
    {
        $mail = (new MemberDeletedByAdminMail('Camille'))->locale('fr');

        $mail->assertHasSubject('Ton compte DLP Friends a été supprimé');
        $mail->assertSeeInHtml('Bonjour Camille');
        $mail->assertSeeInHtml('retirées des systèmes actifs');
    }

    public function test_mail_is_rendered_in_english(): void
    {
        $mail = (new MemberDeletedByAdminMail('Alex'))->locale('en');

        $mail->assertHasSubject('Your DLP Friends account has been deleted');
        $mail->assertSeeInHtml('Hello Alex');
        $mail->assertSeeInHtml('removed from active systems');
    }
}
