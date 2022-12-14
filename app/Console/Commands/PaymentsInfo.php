<?php

namespace App\Console\Commands;

use App\Exports\AExport;
use App\Exports\AgreementExport;
use App\Exports\PaymentExport;
use App\Jobs\AgreementsInfoJob;
use App\Jobs\PaymentsInfoJob;
use App\Jobs\SendEmailJob;
use App\Models\Agreement;
use App\Models\File;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class PaymentsInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'All payments were updated';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $addedFiles = File::all()->toArray();
        $files = glob(storage_path('app/public/documents/*.{xml}'), GLOB_BRACE);

        foreach ($files as $file) {
            if (in_array($file, array_column($addedFiles, 'filename'))) {
                continue;
            }
            else {
                $xml = simplexml_load_file($file) or die('can\'t load xml');
                $array = [];
                $payments = [];
                foreach ($xml as $record) {
                    foreach ($record->attributes() as $a => $b) {
                        $array += [
                            $a => (string)$b,
                        ];
                    }
                }

                foreach ($xml->Payments->PaymentSchedule as $a) {
                    foreach ($a->P1 as $b) {
                        array_push($payments, [$b->attributes()->getName() => (int)$b->attributes()->PaymentNumber]);
                    }
                }

                foreach ($payments as $payment => $val) {
                    foreach ($xml->Payments->PaymentSchedule->P1 as $a) {
                        foreach ($a as $b) {
                            if ($val['PaymentNumber'] === (int)$a->attributes()) {
                                $payments[$payment] += [
                                    $b->attributes()->getName() => (string)$b->attributes(),
                                ];
                            }
                        }
                    }
                }
                $dateArray = [];
                $pattern = '/([0-9]?[0-9])[\.\-\/ ]+([0-1]?[0-9])[\.\-\/ ]+([0-9]{2,4})/';
                preg_match_all($pattern, $array['Validity'], $dateArray);
                if (!empty($array)) {
                    $agreement = Agreement::create([
                        'leasing_subject' => $array['LeasingSubject'],
                        'contract_cost' => $array['ContractCost'],
                        'payment_amount' => $array['PaymentAmount'],
                        'total_amount' => $array['TotalAmount'],
                        'validity_start' => $dateArray[0][0],
                        'validity_end' => $dateArray[0][1],
                    ]);
                }
                if (!empty($payments)) {
                    foreach ($payments as $payment) {
                        Payment::create([
                            'agreement_id' => Agreement::where('leasing_subject', '=', $array['LeasingSubject'])->value('id'),
                            'payment_number' => $payment['PaymentNumber'],
                            'settlement_month' => $payment['SettlementMonth'],
                            'redemption_payment' => $payment['RedemptionPayment'],
                            'advance_payment_amount' => $payment['AdvancePaymentAmount'],
                            'total_amount' => $payment['TotalAmount'],
                        ]);
                    }
                }
                File::create([
                    'filename' => $file,
                ]);
                echo "$file added\n";
            }
        }
        $agreementExport = Agreement::all();
        Excel::store(new AExport($agreementExport),'public/documents/agreements.xlsx');

        $pdf = PDF::loadView('pdf', ['agreements' => $agreementExport])->setOption(['defaultFont' => 'sans-serif']);
        $pdf->setPaper([0,0,1024,800], 'landscape');
        $content = $pdf->download()->getOriginalContent();
        Storage::put('public/documents/agreements.pdf', $content);

        $files = [];

        array_push($files, glob(storage_path('app/public/documents/agreements.{xlsx}'), GLOB_BRACE));
        array_push($files, glob(storage_path('app/public/documents/agreements.{pdf}'), GLOB_BRACE));

        $details = [
            'email' => 'user1@gmail.loc',
            'files' => $files,
        ];
        SendEmailJob::dispatch($details);
        echo "Command completed successfully\n";
    }
}
