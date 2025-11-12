<?php
// app/Http/Controllers/ReportController.php - UPDATED VERSION
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BirthRecord;
use App\Models\MarriageRecord;
use App\Models\DeathRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function getStatistics()
    {
        try {
            $totalBirths = BirthRecord::where('is_active', true)->count();
            $totalMarriages = MarriageRecord::where('is_active', true)->count();
            $totalDeaths = DeathRecord::where('is_active', true)->count();
            
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;
            
            $monthlyBirths = BirthRecord::where('is_active', true)
                ->whereMonth('date_of_birth', $currentMonth)
                ->whereYear('date_of_birth', $currentYear)
                ->count();
                
            $monthlyMarriages = MarriageRecord::where('is_active', true)
                ->whereMonth('date_of_marriage', $currentMonth)
                ->whereYear('date_of_marriage', $currentYear)
                ->count();
                
            $monthlyDeaths = DeathRecord::where('is_active', true)
                ->whereMonth('date_of_death', $currentMonth)
                ->whereYear('date_of_death', $currentYear)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_births' => $totalBirths,
                    'total_marriages' => $totalMarriages,
                    'total_deaths' => $totalDeaths,
                    'monthly_births' => $monthlyBirths,
                    'monthly_marriages' => $monthlyMarriages,
                    'monthly_deaths' => $monthlyDeaths,
                    'total_records' => $totalBirths + $totalMarriages + $totalDeaths,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }

// app/Http/Controllers/ReportController.php - FIXED getRegistrationsTrend method
public function getRegistrationsTrend(Request $request)
{
    try {
        $period = $request->get('period', 'monthly');
        $year = $request->get('year', Carbon::now()->year);
        $recordType = $request->get('recordType', 'all');
        
        if ($period === 'yearly') {
            // Yearly trends
            $trends = [];
            
            if ($recordType === 'all' || $recordType === 'birth') {
                $birthTrends = BirthRecord::select(
                    DB::raw('YEAR(date_of_birth) as year'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('is_active', true)
                ->groupBy('year')
                ->orderBy('year')
                ->get()
                ->map(function ($item) {
                    $item->type = 'Birth';
                    $item->period = $item->year;
                    return $item;
                });
                $trends = array_merge($trends, $birthTrends->toArray());
            }
            
            if ($recordType === 'all' || $recordType === 'marriage') {
                $marriageTrends = MarriageRecord::select(
                    DB::raw('YEAR(date_of_marriage) as year'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('is_active', true)
                ->groupBy('year')
                ->orderBy('year')
                ->get()
                ->map(function ($item) {
                    $item->type = 'Marriage';
                    $item->period = $item->year;
                    return $item;
                });
                $trends = array_merge($trends, $marriageTrends->toArray());
            }
            
            if ($recordType === 'all' || $recordType === 'death') {
                $deathTrends = DeathRecord::select(
                    DB::raw('YEAR(date_of_death) as year'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('is_active', true)
                ->groupBy('year')
                ->orderBy('year')
                ->get()
                ->map(function ($item) {
                    $item->type = 'Death';
                    $item->period = $item->year;
                    return $item;
                });
                $trends = array_merge($trends, $deathTrends->toArray());
            }
            
        } else {
            // Monthly trends
            $trends = [];
            
            if ($recordType === 'all' || $recordType === 'birth') {
                $birthTrends = BirthRecord::select(
                    DB::raw('MONTH(date_of_birth) as month'),
                    DB::raw('YEAR(date_of_birth) as year'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('is_active', true)
                ->whereYear('date_of_birth', $year)
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    $item->type = 'Birth';
                    $item->period = Carbon::create($item->year, $item->month)->format('M Y');
                    return $item;
                });
                $trends = array_merge($trends, $birthTrends->toArray());
            }
            
            if ($recordType === 'all' || $recordType === 'marriage') {
                $marriageTrends = MarriageRecord::select(
                    DB::raw('MONTH(date_of_marriage) as month'),
                    DB::raw('YEAR(date_of_marriage) as year'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('is_active', true)
                ->whereYear('date_of_marriage', $year)
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    $item->type = 'Marriage';
                    $item->period = Carbon::create($item->year, $item->month)->format('M Y');
                    return $item;
                });
                $trends = array_merge($trends, $marriageTrends->toArray());
            }
            
            if ($recordType === 'all' || $recordType === 'death') {
                $deathTrends = DeathRecord::select(
                    DB::raw('MONTH(date_of_death) as month'),
                    DB::raw('YEAR(date_of_death) as year'),
                    DB::raw('COUNT(*) as count')
                )
                ->where('is_active', true)
                ->whereYear('date_of_death', $year)
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    $item->type = 'Death';
                    $item->period = Carbon::create($item->year, $item->month)->format('M Y');
                    return $item;
                });
                $trends = array_merge($trends, $deathTrends->toArray());
            }
        }

        return response()->json([
            'success' => true,
            'data' => $trends
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch trends: ' . $e->getMessage()
        ], 500);
    }
}

    public function getGenderDistribution()
    {
        try {
            $birthGender = BirthRecord::select(
                'sex',
                DB::raw('COUNT(*) as count')
            )
            ->where('is_active', true)
            ->groupBy('sex')
            ->get()
            ->map(function ($item) {
                $item->type = 'Birth';
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $birthGender
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch gender distribution'
            ], 500);
        }
    }

    public function getMonthlySummary(Request $request)
    {
        try {
            $year = $request->get('year', Carbon::now()->year);
            
            // Get monthly data for all record types
            $monthlyBirths = BirthRecord::select(
                DB::raw('MONTH(date_of_birth) as month'),
                DB::raw('COUNT(*) as births')
            )
            ->where('is_active', true)
            ->whereYear('date_of_birth', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

            $monthlyMarriages = MarriageRecord::select(
                DB::raw('MONTH(date_of_marriage) as month'),
                DB::raw('COUNT(*) as marriages')
            )
            ->where('is_active', true)
            ->whereYear('date_of_marriage', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

            $monthlyDeaths = DeathRecord::select(
                DB::raw('MONTH(date_of_death) as month'),
                DB::raw('COUNT(*) as deaths')
            )
            ->where('is_active', true)
            ->whereYear('date_of_death', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

            // Combine data for all months
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $birthCount = $monthlyBirths->firstWhere('month', $month)->births ?? 0;
                $marriageCount = $monthlyMarriages->firstWhere('month', $month)->marriages ?? 0;
                $deathCount = $monthlyDeaths->firstWhere('month', $month)->deaths ?? 0;
                
                $monthlyData[] = [
                    'month' => $month,
                    'births' => $birthCount,
                    'marriages' => $marriageCount,
                    'deaths' => $deathCount,
                    'total' => $birthCount + $marriageCount + $deathCount
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $monthlyData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch monthly summary'
            ], 500);
        }
    }

    public function getRecordTypeDistribution()
    {
        try {
            $birthCount = BirthRecord::where('is_active', true)->count();
            $marriageCount = MarriageRecord::where('is_active', true)->count();
            $deathCount = DeathRecord::where('is_active', true)->count();

            $distribution = [
                ['type' => 'Birth', 'count' => $birthCount, 'color' => '#018181'],
                ['type' => 'Marriage', 'count' => $marriageCount, 'color' => '#e83e8c'],
                ['type' => 'Death', 'count' => $deathCount, 'color' => '#6c757d']
            ];

            return response()->json([
                'success' => true,
                'data' => $distribution
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch record type distribution'
            ], 500);
        }
    }

public function exportData(Request $request)
{
    try {
        $format = $request->get('format', 'csv');
        $year = $request->get('year', Carbon::now()->year);
        $type = $request->get('type', 'all');
        
        $data = [];
        
        if ($type === 'all' || $type === 'birth') {
            $births = BirthRecord::with(['mother', 'father'])
                ->where('is_active', true)
                ->whereYear('date_of_birth', $year)
                ->get()
                ->map(function ($record) {
                    return [
                        'Record Type' => 'Birth',
                        'Registry Number' => $record->registry_number,
                        'Child First Name' => $record->child_first_name,
                        'Child Middle Name' => $record->child_middle_name,
                        'Child Last Name' => $record->child_last_name,
                        'Sex' => $record->sex,
                        'Date of Birth' => $record->date_of_birth,
                        'Time of Birth' => $record->time_of_birth,
                        'Place of Birth' => $record->place_of_birth,
                        'Birth Address' => $record->birth_address_house . ', ' . $record->birth_address_barangay . ', ' . $record->birth_address_city,
                        'Type of Birth' => $record->type_of_birth,
                        'Birth Weight' => $record->birth_weight,
                        'Mother First Name' => $record->mother ? $record->mother->first_name : 'N/A',
                        'Mother Last Name' => $record->mother ? $record->mother->last_name : 'N/A',
                        'Father First Name' => $record->father ? $record->father->first_name : 'N/A',
                        'Father Last Name' => $record->father ? $record->father->last_name : 'N/A',
                        'Date Registered' => $record->date_registered,
                    ];
                });
            $data = array_merge($data, $births->toArray());
        }
        
        if ($type === 'all' || $type === 'marriage') {
            $marriages = MarriageRecord::with(['husband', 'wife'])
                ->where('is_active', true)
                ->whereYear('date_of_marriage', $year)
                ->get()
                ->map(function ($record) {
                    return [
                        'Record Type' => 'Marriage',
                        'Registry Number' => $record->registry_number,
                        'Husband First Name' => $record->husband ? $record->husband->first_name : 'N/A',
                        'Husband Middle Name' => $record->husband ? $record->husband->middle_name : 'N/A',
                        'Husband Last Name' => $record->husband ? $record->husband->last_name : 'N/A',
                        'Wife First Name' => $record->wife ? $record->wife->first_name : 'N/A',
                        'Wife Middle Name' => $record->wife ? $record->wife->middle_name : 'N/A',
                        'Wife Last Name' => $record->wife ? $record->wife->last_name : 'N/A',
                        'Date of Marriage' => $record->date_of_marriage,
                        'Place of Marriage' => $record->place_of_marriage,
                        'Date Registered' => $record->date_registered,
                    ];
                });
            $data = array_merge($data, $marriages->toArray());
        }
        
        if ($type === 'all' || $type === 'death') {
            $deaths = DeathRecord::where('is_active', true)
                ->whereYear('date_of_death', $year)
                ->get()
                ->map(function ($record) {
                    return [
                        'Record Type' => 'Death',
                        'Registry Number' => $record->registry_number,
                        'Deceased First Name' => $record->deceased_first_name,
                        'Deceased Middle Name' => $record->deceased_middle_name,
                        'Deceased Last Name' => $record->deceased_last_name,
                        'Sex' => $record->sex,
                        'Date of Death' => $record->date_of_death,
                        'Place of Death' => $record->place_of_death,
                        'Cause of Death' => $record->cause_of_death,
                        'Date Registered' => $record->date_registered,
                    ];
                });
            $data = array_merge($data, $deaths->toArray());
        }

        // If no data found, return appropriate message
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No data found for the selected criteria'
            ], 404);
        }

        if ($format === 'csv') {
            return $this->exportToCsv($data, "civil-registry-{$type}-{$year}.csv");
        }

        // For now, only CSV is supported
        return response()->json([
            'success' => false,
            'message' => 'Only CSV export is currently supported'
        ], 400);

    } catch (\Exception $e) {
        Log::error('Export failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Export failed: ' . $e->getMessage()
        ], 500);
    }
}

private function exportToCsv($data, $filename)
{
    $headers = [
        'Content-Type' => 'text/csv; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ];

    $callback = function() use ($data) {
        $file = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 to help Excel with special characters
        fputs($file, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        
        // Add headers
        if (count($data) > 0) {
            fputcsv($file, array_keys($data[0]));
        }
        
        // Add data
        foreach ($data as $row) {
            // Ensure all values are properly formatted
            $formattedRow = array_map(function ($value) {
                if (is_null($value)) return '';
                if ($value instanceof \DateTime) return $value->format('Y-m-d');
                return $value;
            }, $row);
            
            fputcsv($file, $formattedRow);
        }
        
        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
}
}