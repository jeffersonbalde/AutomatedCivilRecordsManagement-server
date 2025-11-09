<?php
namespace App\Mail;

use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffAccountReactivated extends Mailable
{
    use Queueable, SerializesModels;

    public $staff;

    public function __construct(Staff $staff)
    {
        $this->staff = $staff;
    }

    public function build()
    {
        return $this->subject('Your Account Has Been Reactivated')
                    ->markdown('emails.staff.account-reactivated')
                    ->with([
                        'staff' => $this->staff,
                        'loginUrl' => url('/')
                    ]);
    }
}