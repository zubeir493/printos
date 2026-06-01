<?php

namespace App\Services\Proformas;

use App\Mail\ProformaGenerated;
use App\Models\Bank;
use App\Models\EmailLog;
use App\Models\Proforma;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProformaPdfService
{
    public function generate(Proforma $proforma): array
    {
        $pdf = $this->buildPdf($proforma);

        $filename = $this->filenameFor($proforma);
        $path = "proformas/{$filename}";

        Storage::disk('local')->put($path, $pdf->output());

        $proforma->updateQuietly([
            'filename' => $filename,
            'file_path' => $path,
        ]);

        return [
            'filename' => $filename,
            'path' => $path,
            'pdf' => $pdf,
            'proforma_data' => $this->dataFor($proforma),
        ];
    }

    private function buildPdf(Proforma $proforma): \Barryvdh\DomPDF\PDF
    {
        $proforma->loadMissing(['partner', 'tasks']);

        return Pdf::loadView('proformas.pdf', [
            'proformaData' => $this->dataFor($proforma),
        ])
            ->setPaper('a4')
            ->setOption('defaultFont', 'Arial')
            ->setOption('fontDir', public_path('fonts'))
            ->setOption('fontCache', public_path('fonts'))
            ->setOption('isRemoteEnabled', true);
    }

    public function downloadUrl(Proforma $proforma): ?string
    {
        return route('proformas.download', ['proforma' => $proforma]);
    }

    public function downloadResponse(Proforma $proforma): BinaryFileResponse
    {
        $tempPath = 'proformas-temp/'.Str::uuid()->toString().'.pdf';

        Storage::disk('local')->put($tempPath, $this->buildPdf($proforma)->output());

        return response()->download(
            Storage::disk('local')->path($tempPath),
            $this->filenameFor($proforma),
            ['Content-Type' => 'application/pdf'],
        )->deleteFileAfterSend(true);
    }

    public function email(Proforma $proforma, string $recipientEmail, ?string $message = null): void
    {
        $payload = $this->generate($proforma);

        Mail::to($recipientEmail)
            ->send(new ProformaGenerated($payload, ['message' => $message]));

        $proforma->update([
            'status' => $proforma->status === 'draft' ? 'sent' : $proforma->status,
            'emailed_at' => now(),
            'email_recipient' => $recipientEmail,
        ]);

        EmailLog::create([
            'recipient_email' => $recipientEmail,
            'subject' => 'Proforma #'.$proforma->proforma_number,
            'message' => $message,
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);
    }

    public function dataFor(Proforma $proforma): array
    {
        $settings = Setting::getSettings();
        $companyInfo = $settings->getCompanyInfo();
        $validityDays = $proforma->issue_date && $proforma->expiry_date
            ? $proforma->issue_date->diffInDays($proforma->expiry_date)
            : null;

        return [
            'proforma_number' => $proforma->proforma_number,
            'issue_date' => $proforma->issue_date?->format('d/m/Y'),
            'expiry_date' => $proforma->expiry_date?->format('d/m/Y'),
            'validity_days' => $validityDays,
            'status' => $proforma->status,
            'company_info' => $companyInfo,
            'customer_info' => [
                'name' => $proforma->partner?->name ?? 'Internal Job',
                'address' => $proforma->partner?->address ?? '',
                'phone' => $proforma->partner?->phone ?? '',
                'email' => $proforma->partner?->email ?? '',
                'tin_number' => $proforma->partner?->tin_number ?? '',
            ],
            'items' => $proforma->tasks
                ->map(fn ($task): array => [
                    'name' => $task->name,
                    'quantity' => $task->quantity,
                    'unit' => $task->size ?: 'Pcs',
                    'unit_price' => (float) $task->unit_price,
                    'total' => (float) $task->task_cost,
                ])
                ->all(),
            'subtotal' => (float) $proforma->subtotal,
            'tax_amount' => (float) $proforma->tax_amount,
            'total' => (float) $proforma->total,
            'amount_in_words' => $this->amountInWords((float) $proforma->total),
            'remarks' => $proforma->remarks,
            'currency_code' => $settings->currency_code ?? 'Birr',
            'currency_symbol' => $settings->currency_symbol ?? 'Birr',
            'vat_rate' => (float) ($settings->vat_rate ?? 15),
            'bank_accounts' => Bank::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['name', 'bank_name', 'account_number'])
                ->map(fn (Bank $bank): array => [
                    'name' => $bank->bank_name ?: $bank->name,
                    'account_number' => $bank->account_number,
                ])
                ->all(),
        ];
    }

    private function filenameFor(Proforma $proforma): string
    {
        $number = Str::of($proforma->proforma_number ?: "proforma-{$proforma->id}")
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "proforma-{$number}.pdf";
    }

    private function amountInWords(float $amount): string
    {
        $wholeAmount = (int) round($amount);

        return ucfirst($this->numberToWords($wholeAmount).' birr');
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $units = [
            '',
            'one',
            'two',
            'three',
            'four',
            'five',
            'six',
            'seven',
            'eight',
            'nine',
            'ten',
            'eleven',
            'twelve',
            'thirteen',
            'fourteen',
            'fifteen',
            'sixteen',
            'seventeen',
            'eighteen',
            'nineteen',
        ];

        $tens = [
            '',
            '',
            'twenty',
            'thirty',
            'forty',
            'fifty',
            'sixty',
            'seventy',
            'eighty',
            'ninety',
        ];

        if ($number < 20) {
            return $units[$number];
        }

        if ($number < 100) {
            return trim($tens[intdiv($number, 10)].' '.$units[$number % 10]);
        }

        if ($number < 1000) {
            $remainder = $number % 100;

            return trim($units[intdiv($number, 100)].' hundred'.($remainder > 0 ? ' '.$this->numberToWords($remainder) : ''));
        }

        foreach ([
            1_000_000_000 => 'billion',
            1_000_000 => 'million',
            1_000 => 'thousand',
        ] as $value => $label) {
            if ($number >= $value) {
                $remainder = $number % $value;

                return trim($this->numberToWords(intdiv($number, $value))." {$label}".($remainder > 0 ? ' '.$this->numberToWords($remainder) : ''));
            }
        }

        return '';
    }
}
