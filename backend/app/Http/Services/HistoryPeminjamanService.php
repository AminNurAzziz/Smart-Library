<?php

namespace App\Http\Services;

use App\Models\Peminjaman;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class HistoryPeminjamanService
{
    public function getHistoryByNIM($nim)
    {
        $user = Auth::user();
        Log::info("Entering getHistoryByNIM function with NIM: {$nim}");

        $historyQuery = Peminjaman::where('nim', $nim)
            ->where('status', 'dikembalikan');
        Log::info("Query result for peminjaman: {$historyQuery->count()} records found");


        if (!is_null($user) && $user->role === 'students') {
            Log::info('User role is students, filtering by user id', ['user_id' => $user->id]);
            $historyQuery->where('user_id', $user->id);
        }

        $history = $historyQuery->get();

        if ($history->isEmpty()) {
            Log::info("No history peminjaman found for NIM {$nim}");
            return collect();
        }

        $result = $history->map(function ($h) {
            return [
                'nim' => $h->nim,
                'kode_pinjam' => $h->kode_pinjam,
                'tgl_pinjam' => $h->tgl_pinjam,
                'tgl_kembali' => $h->tgl_kembali,
                'status' => $h->status,
            ];
        });

        Log::info("Transformed result for history peminjaman: {$result->count()} records found");

        return $result;
    }

    public function getAllHistory(Request $request)
    {
        try {
            $user = Auth::user();
            $pageSize = min($request->input('page_size', 1), 50);
            $currentPage = $request->input('page', 1);

            if (!is_numeric($currentPage) || $currentPage < 1) {
                return response()->json([
                    'statusCode' => 400,
                    'status' => false,
                    'message' => 'Invalid page size number. Page size number must be a > 1 positive integer.'
                ], 400);
            }

            if ($pageSize == -1 || !is_numeric($pageSize) || $pageSize < 1) {
                return response()->json([
                    'statusCode' => 400,
                    'status' => false,
                    'message' => 'Invalid page size number. Page size number must be a > 1 positive integer.'
                ], 400);
            }

            $allHistoryQuery = Peminjaman::where('status', 'dikembalikan');
            Log::info("Query result for peminjaman: {$allHistoryQuery->count()} records found");

            // Filter berdasarkan role admin
            if (!is_null($user) && $user->role === 'admin') {
                Log::info('User role is admin, fetching all history');
            } else {
                // Jika bukan admin, filter berdasarkan user_id pengguna yang login
                $allHistoryQuery->where('user_id', $user->id);
            }
            $allHistory = $allHistoryQuery->paginate($pageSize, ['*'], 'page', $currentPage);

            Log::info('History Fetched', ['total' => $allHistory->total()]);

            $response = $allHistory->map(function ($ah) {
                return [
                    'nim' => $ah->nim,
                    'kode_pinjam' => $ah->kode_pinjam,
                    'tgl_pinjam' => $ah->tgl_pinjam,
                    'tgl_kembali' => $ah->tgl_kembali,
                    'status' => $ah->status,
                ];
            });

            $paginationData = [
                'total_rows' => $allHistory->total(),
                'total_page' => $allHistory->lastPage(),
                'current_page' => $allHistory->currentPage(),
                'page_size' => $allHistory->perPage(),
            ];

            return response()->json([
                'statusCode' => 200,
                'status' => true,
                'data' => $response,
                'message' => 'Success Fetching History',
                'pagination' => $paginationData
            ]);
        } catch (\Exception $e) {
            Log::error('History Fetch Failed', ['error' => $e->getMessage()]);
            return response()->json([
                'statusCode' => 500,
                'status' => false,
                'message' => 'Internal Server Error',
                'error' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    public function deleteHistory($peminjaman)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'statusCode' => 403,
                'status' => false,
                'message' => 'You are not authorized to perform this action.'
            ], 403);
        }

        try {
            $history = Peminjaman::where('status', 'dikembalikan')->where('id', $peminjaman)->first();
            // dd($history);
            Log::info('History Found', ['peminjaman_id' => $peminjaman]);
            if (!$history) {
                return response()->json([
                    'statusCode' => 400,
                    'status' => false,
                    'message' => 'History not found.'
                ], 400);
            }
            $history->delete();
            Log::info('History Deleted', ['history_id' => $history->id]);
            return response()->json([
                'statusCode' => 200,
                'status' => true,
                'message' => 'History deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete History Failed', ['error' => $e->getMessage()]);
            return response()->json([
                'statusCode' => 500,
                'status' => false,
                'message' => 'Internal Server Error',
                'error' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }
}