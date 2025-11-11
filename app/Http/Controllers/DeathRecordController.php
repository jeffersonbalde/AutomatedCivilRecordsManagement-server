<?php
// app/Http/Controllers/DeathRecordController.php
namespace App\Http\Controllers;

use App\Models\DeathRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class DeathRecordController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DeathRecord::with(['encodedByStaff', 'encodedByAdmin'])->active();

            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->search($searchTerm);
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->where('date_of_death', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->where('date_of_death', '<=', $request->date_to);
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 1000);

            $records = $query->paginate($perPage);

            $transformedRecords = $records->getCollection()->map(function ($record) {
                return $this->transformDeathRecord($record);
            });

            $records->setCollection($transformedRecords);

            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve death records: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string',
                'last_name' => 'required|string',
                'date_of_death' => 'required|date',
                'date_of_birth' => 'required|date',
                'exclude_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Exact duplicate check
            $query = DeathRecord::where('first_name', $request->first_name)
                ->where('last_name', $request->last_name)
                ->where('date_of_death', $request->date_of_death)
                ->where('date_of_birth', $request->date_of_birth)
                ->active();

            if ($request->has('exclude_id') && $request->exclude_id) {
                $query->where('id', '!=', $request->exclude_id);
            }

            $exactDuplicate = $query->first();

            // Similar records check
            $similarRecords = DeathRecord::where(function ($query) use ($request) {
                $query->where('first_name', $request->first_name)
                    ->where('last_name', 'like', "%{$request->last_name}%");
            })
            ->orWhere(function ($query) use ($request) {
                $query->where('first_name', 'like', "%{$request->first_name}%")
                    ->where('last_name', $request->last_name);
            })
            ->orWhere('date_of_birth', $request->date_of_birth)
            ->orWhere('date_of_death', $request->date_of_death)
            ->active()
            ->limit(10)
            ->get(['id', 'registry_number', 'first_name', 'middle_name', 'last_name', 'date_of_death', 'date_of_birth', 'sex']);

            return response()->json([
                'success' => true,
                'is_duplicate' => !is_null($exactDuplicate),
                'duplicate_record' => $exactDuplicate,
                'similar_records' => $similarRecords,
                'checked_fields' => [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'date_of_death' => $request->date_of_death,
                    'date_of_birth' => $request->date_of_birth
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate check failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'sex' => 'required|in:Male,Female',
                'civil_status' => 'required|in:Single,Married,Widowed,Divorced,Annulled',
                'date_of_death' => 'required|date',
                'date_of_birth' => 'required|date',
                'age_years' => 'nullable|integer|min:0',
                'age_months' => 'nullable|integer|min:0|max:11',
                'age_days' => 'nullable|integer|min:0|max:30',
                'age_hours' => 'nullable|integer|min:0|max:23',
                'age_minutes' => 'nullable|integer|min:0|max:59',
                'age_under_1' => 'nullable|boolean',
                'place_of_death' => 'required|string|max:500',
                'religion' => 'nullable|string|max:255',
                'citizenship' => 'required|string|max:255',
                'residence' => 'required|string|max:500',
                'occupation' => 'nullable|string|max:255',
                'father_name' => 'required|string|max:255',
                'mother_maiden_name' => 'required|string|max:255',
                'immediate_cause' => 'required|string|max:255',
                'antecedent_cause' => 'nullable|string|max:255',
                'underlying_cause' => 'nullable|string|max:255',
                'other_significant_conditions' => 'nullable|string|max:255',
                'maternal_condition' => 'nullable|string|max:255',
                'manner_of_death' => 'nullable|string|max:255',
                'place_of_occurrence' => 'nullable|string|max:255',
                'autopsy' => 'nullable|in:Yes,No',
                'attendant' => 'required|string|max:255',
                'attendant_other' => 'nullable|string|max:255',
                'attended_from' => 'nullable|date',
                'attended_to' => 'nullable|date',
                'certifier_name' => 'required|string|max:255',
                'certifier_title' => 'nullable|string|max:255',
                'certifier_address' => 'nullable|string|max:255',
                'certifier_date' => 'nullable|date',
                'attended_deceased' => 'nullable|in:Yes,No',
                'death_occurred_time' => 'nullable|string|max:50',
                'corpse_disposal' => 'nullable|in:Burial,Cremation,Other',
                'burial_permit_number' => 'nullable|string|max:255',
                'burial_permit_date' => 'nullable|date',
                'transfer_permit_number' => 'nullable|string|max:255',
                'transfer_permit_date' => 'nullable|date',
                'cemetery_name' => 'nullable|string|max:255',
                'cemetery_address' => 'nullable|string|max:500',
                'informant_name' => 'required|string|max:255',
                'informant_relationship' => 'required|string|max:255',
                'informant_address' => 'nullable|string|max:255',
                'informant_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $encodedBy = $user->id;

            // Check for duplicates
            $duplicateCheck = DeathRecord::where('first_name', $request->first_name)
                ->where('last_name', $request->last_name)
                ->where('date_of_death', $request->date_of_death)
                ->where('date_of_birth', $request->date_of_birth)
                ->active()
                ->first();

            if ($duplicateCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record found. A record with the same name and dates already exists.',
                    'is_duplicate' => true,
                    'existing_record' => $duplicateCheck
                ], 409);
            }

            $registryNumber = $this->generateRegistryNumber();

            $deathRecord = DeathRecord::create(array_merge($request->all(), [
                'registry_number' => $registryNumber,
                'date_registered' => now(),
                'encoded_by' => $encodedBy,
            ]));

            DB::commit();

            $deathRecord->load(['encodedByStaff', 'encodedByAdmin']);
            $transformedRecord = $this->transformDeathRecord($deathRecord);

            return response()->json([
                'success' => true,
                'message' => 'Death record saved successfully!',
                'data' => $transformedRecord,
                'registry_number' => $registryNumber
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save death record: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'sex' => 'required|in:Male,Female',
                'civil_status' => 'required|in:Single,Married,Widowed,Divorced,Annulled',
                'date_of_death' => 'required|date',
                'date_of_birth' => 'required|date',
                'age_years' => 'nullable|integer|min:0',
                'age_months' => 'nullable|integer|min:0|max:11',
                'age_days' => 'nullable|integer|min:0|max:30',
                'age_hours' => 'nullable|integer|min:0|max:23',
                'age_minutes' => 'nullable|integer|min:0|max:59',
                'age_under_1' => 'nullable|boolean',
                'place_of_death' => 'required|string|max:500',
                'religion' => 'nullable|string|max:255',
                'citizenship' => 'required|string|max:255',
                'residence' => 'required|string|max:500',
                'occupation' => 'nullable|string|max:255',
                'father_name' => 'required|string|max:255',
                'mother_maiden_name' => 'required|string|max:255',
                'immediate_cause' => 'required|string|max:255',
                'antecedent_cause' => 'nullable|string|max:255',
                'underlying_cause' => 'nullable|string|max:255',
                'other_significant_conditions' => 'nullable|string|max:255',
                'maternal_condition' => 'nullable|string|max:255',
                'manner_of_death' => 'nullable|string|max:255',
                'place_of_occurrence' => 'nullable|string|max:255',
                'autopsy' => 'nullable|in:Yes,No',
                'attendant' => 'required|string|max:255',
                'attendant_other' => 'nullable|string|max:255',
                'attended_from' => 'nullable|date',
                'attended_to' => 'nullable|date',
                'certifier_name' => 'required|string|max:255',
                'certifier_title' => 'nullable|string|max:255',
                'certifier_address' => 'nullable|string|max:255',
                'certifier_date' => 'nullable|date',
                'attended_deceased' => 'nullable|in:Yes,No',
                'death_occurred_time' => 'nullable|string|max:50',
                'corpse_disposal' => 'nullable|in:Burial,Cremation,Other',
                'burial_permit_number' => 'nullable|string|max:255',
                'burial_permit_date' => 'nullable|date',
                'transfer_permit_number' => 'nullable|string|max:255',
                'transfer_permit_date' => 'nullable|date',
                'cemetery_name' => 'nullable|string|max:255',
                'cemetery_address' => 'nullable|string|max:500',
                'informant_name' => 'required|string|max:255',
                'informant_relationship' => 'required|string|max:255',
                'informant_address' => 'nullable|string|max:255',
                'informant_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $deathRecord = DeathRecord::active()->findOrFail($id);

            // Check for duplicates (excluding current record)
            $duplicateCheck = DeathRecord::where('first_name', $request->first_name)
                ->where('last_name', $request->last_name)
                ->where('date_of_death', $request->date_of_death)
                ->where('date_of_birth', $request->date_of_birth)
                ->where('id', '!=', $id)
                ->active()
                ->first();

            if ($duplicateCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record found. Another record with the same name and dates already exists.',
                    'is_duplicate' => true,
                    'existing_record' => $duplicateCheck
                ], 409);
            }

            $deathRecord->update($request->all());

            DB::commit();

            $deathRecord->load(['encodedByStaff', 'encodedByAdmin']);
            $transformedRecord = $this->transformDeathRecord($deathRecord);

            return response()->json([
                'success' => true,
                'message' => 'Death record updated successfully!',
                'data' => $transformedRecord
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update death record: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $record = DeathRecord::with(['encodedByStaff', 'encodedByAdmin'])->active()->findOrFail($id);
            $transformedRecord = $this->transformDeathRecord($record);

            return response()->json([
                'success' => true,
                'data' => $transformedRecord,
                'message' => 'Death record retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Death record not found'
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $record = DeathRecord::active()->findOrFail($id);
            $record->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Death record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete death record'
            ], 500);
        }
    }

    public function statistics()
    {
        try {
            $totalRecords = DeathRecord::active()->count();
            $maleCount = DeathRecord::active()->where('sex', 'Male')->count();
            $femaleCount = DeathRecord::active()->where('sex', 'Female')->count();
            $thisMonth = DeathRecord::active()
                ->whereYear('created_at', date('Y'))
                ->whereMonth('created_at', date('m'))
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $totalRecords,
                    'male_count' => $maleCount,
                    'female_count' => $femaleCount,
                    'this_month' => $thisMonth,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }

    private function generateRegistryNumber()
    {
        $year = date('Y');

        $lastRecord = DeathRecord::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastRecord ?
            (int) substr($lastRecord->registry_number, -5) + 1 : 1;

        return "DR-{$year}-" . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    private function transformDeathRecord($record)
    {
        $encoderInfo = $this->getEncoderInfo($record);

        return [
            'id' => $record->id,
            'registry_number' => $record->registry_number,
            'first_name' => $record->first_name,
            'middle_name' => $record->middle_name,
            'last_name' => $record->last_name,
            'sex' => $record->sex,
            'civil_status' => $record->civil_status,
            'date_of_death' => $record->date_of_death,
            'date_of_birth' => $record->date_of_birth,
            'age_years' => $record->age_years,
            'age_months' => $record->age_months,
            'age_days' => $record->age_days,
            'age_hours' => $record->age_hours,
            'age_minutes' => $record->age_minutes,
            'age_under_1' => $record->age_under_1,
            'place_of_death' => $record->place_of_death,
            'religion' => $record->religion,
            'citizenship' => $record->citizenship,
            'residence' => $record->residence,
            'occupation' => $record->occupation,
            'father_name' => $record->father_name,
            'mother_maiden_name' => $record->mother_maiden_name,
            'immediate_cause' => $record->immediate_cause,
            'antecedent_cause' => $record->antecedent_cause,
            'underlying_cause' => $record->underlying_cause,
            'other_significant_conditions' => $record->other_significant_conditions,
            'maternal_condition' => $record->maternal_condition,
            'manner_of_death' => $record->manner_of_death,
            'place_of_occurrence' => $record->place_of_occurrence,
            'autopsy' => $record->autopsy,
            'attendant' => $record->attendant,
            'attendant_other' => $record->attendant_other,
            'attended_from' => $record->attended_from,
            'attended_to' => $record->attended_to,
            'certifier_name' => $record->certifier_name,
            'certifier_title' => $record->certifier_title,
            'certifier_address' => $record->certifier_address,
            'certifier_date' => $record->certifier_date,
            'attended_deceased' => $record->attended_deceased,
            'death_occurred_time' => $record->death_occurred_time,
            'corpse_disposal' => $record->corpse_disposal,
            'burial_permit_number' => $record->burial_permit_number,
            'burial_permit_date' => $record->burial_permit_date,
            'transfer_permit_number' => $record->transfer_permit_number,
            'transfer_permit_date' => $record->transfer_permit_date,
            'cemetery_name' => $record->cemetery_name,
            'cemetery_address' => $record->cemetery_address,
            'informant_name' => $record->informant_name,
            'informant_relationship' => $record->informant_relationship,
            'informant_address' => $record->informant_address,
            'informant_date' => $record->informant_date,
            'date_registered' => $record->date_registered,
            'is_active' => $record->is_active,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,

            'encoded_by' => $encoderInfo,
            'encoder_name' => $record->encoder_name,
            'encoder_type' => $record->encoder_type,

            'full_name' => $record->full_name,
            'age_at_death' => $record->age_at_death,
        ];
    }

    private function getEncoderInfo($record)
    {
        $encoderInfo = [
            'id' => null,
            'full_name' => 'System',
            'user_type' => 'System',
            'email' => null,
            'position' => 'System Account'
        ];

        if ($record->relationLoaded('encodedByAdmin') && $record->encodedByAdmin) {
            $encoderInfo = [
                'id' => $record->encodedByAdmin->id,
                'full_name' => $record->encodedByAdmin->full_name,
                'user_type' => 'Admin',
                'email' => $record->encodedByAdmin->email,
                'position' => $record->encodedByAdmin->position ?? 'System Administrator'
            ];
        }
        elseif ($record->relationLoaded('encodedByStaff') && $record->encodedByStaff) {
            $encoderInfo = [
                'id' => $record->encodedByStaff->id,
                'full_name' => $record->encodedByStaff->full_name,
                'user_type' => 'Staff',
                'email' => $record->encodedByStaff->email,
                'position' => 'Registry Staff'
            ];
        }
        elseif ($record->encoder_name && $record->encoder_name !== 'System') {
            $encoderInfo = [
                'id' => $record->encoded_by,
                'full_name' => $record->encoder_name,
                'user_type' => $record->encoder_type,
                'email' => null,
                'position' => $record->encoder_type === 'Admin' ? 'System Administrator' : 'Registry Staff'
            ];
        }

        return $encoderInfo;
    }
}