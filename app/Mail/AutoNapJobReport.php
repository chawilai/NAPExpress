<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AutoNapJobReport extends Mailable
{
    /**
     * @param  array{
     *     jobId: string,
     *     site: string,
     *     formType: string,
     *     napDisplayName: string,
     *     startedAt: string,
     *     finishedAt: string,
     *     durationSeconds: int,
     *     avgSecondsPerRecord: float,
     *     total: int,
     *     success: int,
     *     failed: int,
     *     results: array<int, array{id_card: string, success: bool, nap_code: ?string, error: ?string, uic: ?string}>,
     * }  $report
     */
    public function __construct(public array $report) {}

    public function envelope(): Envelope
    {
        $status = $this->report['failed'] > 0 ? 'partial' : 'success';
        $statusLabel = $status === 'success' ? 'Success' : 'Partial';
        $type = $this->report['formType'];
        $site = $this->report['site'];

        return new Envelope(
            subject: "{$type} Report — {$site} ({$statusLabel} {$this->report['success']}/{$this->report['total']})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.autonap-report',
            with: ['report' => $this->report],
        );
    }
}
