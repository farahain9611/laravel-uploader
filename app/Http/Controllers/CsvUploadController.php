<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCsvUpload;
use App\Models\CsvUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CsvUploadController extends Controller
{
    public function index()
    {
        $uploads = CsvUpload::latest()->get();
        return view('csv-uploads.index', compact('uploads'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt'
            ]);

            $file = $request->file('csv_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Store the file
            $file->storeAs('uploads', $filename);

            // Create upload record
            $upload = CsvUpload::create([
                'filename' => $filename,
                'status' => 'pending'
            ]);

            // Dispatch job
            ProcessCsvUpload::dispatch($upload);

            return response()->json([
                'message' => 'File uploaded successfully',
                'upload' => $upload
            ]);
        } catch (\Exception $e) {
            Log::error('CSV Upload Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function status()
    {
        $uploads = CsvUpload::latest()->get();
        return response()->json($uploads);
    }
} 