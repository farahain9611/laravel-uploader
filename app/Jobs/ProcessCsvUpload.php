<?php

namespace App\Jobs;

use App\Events\CsvUploadStatusUpdated;
use App\Models\CsvUpload;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\CharsetConverter;

class ProcessCsvUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $csvUpload;

    public function __construct(CsvUpload $csvUpload)
    {
        $this->csvUpload = $csvUpload;
    }

    public function handle()
    {
        try {
            $this->updateStatus('processing');

            // Get the file path from storage
            $filePath = Storage::path('uploads/' . $this->csvUpload->filename);
            
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$this->csvUpload->filename}");
            }

            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);

            // Convert to UTF-8
            $converter = new CharsetConverter();
            $converter->inputEncoding('UTF-8');
            $converter->outputEncoding('UTF-8');
            $csv->addFormatter($converter);

            foreach ($csv->getRecords() as $record) {
                Product::updateOrCreate(
                    ['unique_key' => $record['UNIQUE_KEY']],
                    [
                        'product_title' => $record['PRODUCT_TITLE'],
                        'product_description' => $record['PRODUCT_DESCRIPTION'],
                        'style_number' => $record['STYLE#'],
                        'sanmar_mainframe_color' => $record['SANMAR_MAINFRAME_COLOR'],
                        'size' => $record['SIZE'],
                        'color_name' => $record['COLOR_NAME'],
                        'piece_price' => $record['PIECE_PRICE'],
                    ]
                );
            }

            $this->updateStatus('completed');
        } catch (\Exception $e) {
            $this->updateStatus('failed', $e->getMessage());
            throw $e;
        }
    }

    protected function updateStatus($status, $errorMessage = null)
    {
        $this->csvUpload->update([
            'status' => $status,
            'error_message' => $errorMessage
        ]);

        broadcast(new CsvUploadStatusUpdated($this->csvUpload->fresh()));
    }
} 