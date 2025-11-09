<?php
namespace App\Mail;

use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffAccountDeactivated extends Mailable
{
    use Queueable, SerializesModels;

    public $staff;
    public $reason;

    public function __construct(Staff $staff, $reason)
    {
        $this->staff = $staff;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('Your Account Has Been Deactivated')
                    ->markdown('emails.staff.account-deactivated')
                    ->with([
                        'staff' => $this->staff,
                        'reason' => $this->reason
                    ]);
    }
}