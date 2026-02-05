<?php
// app/Http/Controllers/MarriageRecordController.php
namespace App\Http\Controllers;

use App\Models\MarriageRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class MarriageRecordController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = MarriageRecord::with([
                'encodedByStaff',
                'encodedByAdmin'
            ])->active();

            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->search($searchTerm);
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->where('date_of_marriage', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->where('date_of_marriage', '<=', $request->date_to);
            }

            // Phase 2: Place filter (not name only)
            if ($request->filled('place_of_marriage')) {
                $query->where('place_of_marriage', 'like', '%' . $request->place_of_marriage . '%');
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 1000);

            $records = $query->paginate($perPage);

            $transformedRecords = $records->getCollection()->map(function ($record) {
                return $this->transformMarriageRecord($record);
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
                'message' => 'Failed to retrieve marriage records: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'husband_first_name' => 'required|string',
                'husband_last_name' => 'required|string',
                'wife_first_name' => 'required|string',
                'wife_last_name' => 'required|string',
                'date_of_marriage' => 'required|date',
                'place_of_marriage' => 'nullable|string',
                'exclude_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Phase 2: Exact duplicate = names + date + place of marriage (not name only)
            $query = MarriageRecord::where('husband_first_name', $request->husband_first_name)
                ->where('husband_last_name', $request->husband_last_name)
                ->where('wife_first_name', $request->wife_first_name)
                ->where('wife_last_name', $request->wife_last_name)
                ->where('date_of_marriage', $request->date_of_marriage)
                ->active();
            if ($request->filled('place_of_marriage')) {
                $query->where('place_of_marriage', $request->place_of_marriage);
            }

            if ($request->has('exclude_id') && $request->exclude_id) {
                $query->where('id', '!=', $request->exclude_id);
            }

            $exactDuplicate = $query->first();

            $similarRecords = MarriageRecord::where(function ($query) use ($request) {
                $query->where('husband_first_name', $request->husband_first_name)
                    ->where('husband_last_name', 'like', "%{$request->husband_last_name}%")
                    ->where('wife_first_name', $request->wife_first_name)
                    ->where('wife_last_name', 'like', "%{$request->wife_last_name}%");
            })
                ->orWhere(function ($query) use ($request) {
                    $query->where('husband_first_name', 'like', "%{$request->husband_first_name}%")
                        ->where('husband_last_name', $request->husband_last_name)
                        ->where('wife_first_name', 'like', "%{$request->wife_first_name}%")
                        ->where('wife_last_name', $request->wife_last_name);
                })
                ->active()
                ->limit(10)
                ->get(['id', 'registry_number', 'husband_first_name', 'husband_last_name', 'wife_first_name', 'wife_last_name', 'date_of_marriage', 'place_of_marriage']);

            return response()->json([
                'success' => true,
                'is_duplicate' => !is_null($exactDuplicate),
                'duplicate_record' => $exactDuplicate,
                'similar_records' => $similarRecords,
                'checked_fields' => [
                    'husband_first_name' => $request->husband_first_name,
                    'husband_last_name' => $request->husband_last_name,
                    'wife_first_name' => $request->wife_first_name,
                    'wife_last_name' => $request->wife_last_name,
                    'date_of_marriage' => $request->date_of_marriage,
                    'place_of_marriage' => $request->place_of_marriage,
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
                // Basic Information
                'province' => 'required|string|max:255',
                'city_municipality' => 'required|string|max:255',
                'date_of_marriage' => 'required|date',
                'time_of_marriage' => 'required|date_format:H:i',
                'place_of_marriage' => 'required|string|max:255',
                'marriage_type' => 'required|in:Civil,Church,Tribal,Other',
                'license_number' => 'required|string|max:255',
                'license_date' => 'required|date',
                'license_place' => 'required|string|max:255',
                'property_regime' => 'required|in:Absolute Community,Conjugal Partnership,Separation of Property,Other',

                // Husband Information
                'husband_first_name' => 'required|string|max:255',
                'husband_middle_name' => 'nullable|string|max:255',
                'husband_last_name' => 'required|string|max:255',
                'husband_birthdate' => 'required|date',
                'husband_birthplace' => 'required|string|max:255',
                'husband_sex' => 'required|in:Male,Female',
                'husband_citizenship' => 'required|string|max:255',
                'husband_religion' => 'nullable|string|max:255',
                'husband_civil_status' => 'required|in:Single,Widowed,Divorced,Annulled',
                'husband_occupation' => 'nullable|string|max:255',
                'husband_address' => 'required|string',

                // Husband Parents
                'husband_father_name' => 'required|string|max:255',
                'husband_father_citizenship' => 'required|string|max:255',
                'husband_mother_name' => 'required|string|max:255',
                'husband_mother_citizenship' => 'required|string|max:255',

                // Husband Consent
                'husband_consent_giver' => 'nullable|string|max:255',
                'husband_consent_relationship' => 'nullable|string|max:255',
                'husband_consent_address' => 'nullable|string|max:255',

                // Wife Information
                'wife_first_name' => 'required|string|max:255',
                'wife_middle_name' => 'nullable|string|max:255',
                'wife_last_name' => 'required|string|max:255',
                'wife_birthdate' => 'required|date',
                'wife_birthplace' => 'required|string|max:255',
                'wife_sex' => 'required|in:Male,Female',
                'wife_citizenship' => 'required|string|max:255',
                'wife_religion' => 'nullable|string|max:255',
                'wife_civil_status' => 'required|in:Single,Widowed,Divorced,Annulled',
                'wife_occupation' => 'nullable|string|max:255',
                'wife_address' => 'required|string',

                // Wife Parents
                'wife_father_name' => 'required|string|max:255',
                'wife_father_citizenship' => 'required|string|max:255',
                'wife_mother_name' => 'required|string|max:255',
                'wife_mother_citizenship' => 'required|string|max:255',

                // Wife Consent
                'wife_consent_giver' => 'nullable|string|max:255',
                'wife_consent_relationship' => 'nullable|string|max:255',
                'wife_consent_address' => 'nullable|string|max:255',

                // Ceremony Details
                'officiating_officer' => 'required|string|max:255',
                'officiant_title' => 'nullable|string|max:255',
                'officiant_license' => 'nullable|string|max:255',

                // Legal Basis
                'legal_basis' => 'nullable|string|max:255',
                'legal_basis_article' => 'nullable|string|max:255',

                // Witnesses
                'witness1_name' => 'required|string|max:255',
                'witness1_address' => 'required|string|max:255',
                'witness1_relationship' => 'nullable|string|max:255',
                'witness2_name' => 'required|string|max:255',
                'witness2_address' => 'required|string|max:255',
                'witness2_relationship' => 'nullable|string|max:255',

                // Additional
                'marriage_remarks' => 'nullable|string',
            ]);

            $user = Auth::user();
            $encodedBy = $user->id;

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Phase 2: Duplicate = names + date + place of marriage (not name only)
            $dupQuery = MarriageRecord::where('husband_first_name', $request->husband_first_name)
                ->where('husband_last_name', $request->husband_last_name)
                ->where('wife_first_name', $request->wife_first_name)
                ->where('wife_last_name', $request->wife_last_name)
                ->where('date_of_marriage', $request->date_of_marriage)
                ->active();
            if ($request->filled('place_of_marriage')) {
                $dupQuery->where('place_of_marriage', $request->place_of_marriage);
            }
            $duplicateCheck = $dupQuery->first();

            if ($duplicateCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record found. A marriage record with the same couple, date, and place already exists.',
                    'is_duplicate' => true,
                    'existing_record' => $duplicateCheck
                ], 409);
            }

            $registryNumber = $this->generateRegistryNumber();

            $marriageRecord = MarriageRecord::create([
                'registry_number' => $registryNumber,
                'province' => $request->province,
                'city_municipality' => $request->city_municipality,
                'date_of_marriage' => $request->date_of_marriage,
                'time_of_marriage' => $request->time_of_marriage,
                'place_of_marriage' => $request->place_of_marriage,
                'marriage_type' => $request->marriage_type,
                'license_number' => $request->license_number,
                'license_date' => $request->license_date,
                'license_place' => $request->license_place,
                'property_regime' => $request->property_regime,

                // Husband Information
                'husband_first_name' => $request->husband_first_name,
                'husband_middle_name' => $request->husband_middle_name,
                'husband_last_name' => $request->husband_last_name,
                'husband_birthdate' => $request->husband_birthdate,
                'husband_birthplace' => $request->husband_birthplace,
                'husband_sex' => $request->husband_sex,
                'husband_citizenship' => $request->husband_citizenship,
                'husband_religion' => $request->husband_religion,
                'husband_civil_status' => $request->husband_civil_status,
                'husband_occupation' => $request->husband_occupation,
                'husband_address' => $request->husband_address,

                // Husband Parents
                'husband_father_name' => $request->husband_father_name,
                'husband_father_citizenship' => $request->husband_father_citizenship,
                'husband_mother_name' => $request->husband_mother_name,
                'husband_mother_citizenship' => $request->husband_mother_citizenship,

                // Husband Consent
                'husband_consent_giver' => $request->husband_consent_giver,
                'husband_consent_relationship' => $request->husband_consent_relationship,
                'husband_consent_address' => $request->husband_consent_address,

                // Wife Information
                'wife_first_name' => $request->wife_first_name,
                'wife_middle_name' => $request->wife_middle_name,
                'wife_last_name' => $request->wife_last_name,
                'wife_birthdate' => $request->wife_birthdate,
                'wife_birthplace' => $request->wife_birthplace,
                'wife_sex' => $request->wife_sex,
                'wife_citizenship' => $request->wife_citizenship,
                'wife_religion' => $request->wife_religion,
                'wife_civil_status' => $request->wife_civil_status,
                'wife_occupation' => $request->wife_occupation,
                'wife_address' => $request->wife_address,

                // Wife Parents
                'wife_father_name' => $request->wife_father_name,
                'wife_father_citizenship' => $request->wife_father_citizenship,
                'wife_mother_name' => $request->wife_mother_name,
                'wife_mother_citizenship' => $request->wife_mother_citizenship,

                // Wife Consent
                'wife_consent_giver' => $request->wife_consent_giver,
                'wife_consent_relationship' => $request->wife_consent_relationship,
                'wife_consent_address' => $request->wife_consent_address,

                // Ceremony Details
                'officiating_officer' => $request->officiating_officer,
                'officiant_title' => $request->officiant_title,
                'officiant_license' => $request->officiant_license,

                // Legal Basis
                'legal_basis' => $request->legal_basis,
                'legal_basis_article' => $request->legal_basis_article,

                // Witnesses
                'witness1_name' => $request->witness1_name,
                'witness1_address' => $request->witness1_address,
                'witness1_relationship' => $request->witness1_relationship,
                'witness2_name' => $request->witness2_name,
                'witness2_address' => $request->witness2_address,
                'witness2_relationship' => $request->witness2_relationship,

                // Additional
                'marriage_remarks' => $request->marriage_remarks,

                // System
                'date_registered' => now(),
                'encoded_by' => $encodedBy,
            ]);

            DB::commit();

            $marriageRecord->load([
                'encodedByStaff',
                'encodedByAdmin'
            ]);

            $transformedRecord = $this->transformMarriageRecord($marriageRecord);

            return response()->json([
                'success' => true,
                'message' => 'Marriage record saved successfully!',
                'data' => $transformedRecord,
                'registry_number' => $registryNumber
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save marriage record: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                // Basic Information
                'province' => 'required|string|max:255',
                'city_municipality' => 'required|string|max:255',
                'date_of_marriage' => 'required|date',
                'time_of_marriage' => 'required|date_format:H:i',
                'place_of_marriage' => 'required|string|max:255',
                'marriage_type' => 'required|in:Civil,Church,Tribal,Other',
                'license_number' => 'required|string|max:255',
                'license_date' => 'required|date',
                'license_place' => 'required|string|max:255',
                'property_regime' => 'required|in:Absolute Community,Conjugal Partnership,Separation of Property,Other',

                // Husband Information
                'husband_first_name' => 'required|string|max:255',
                'husband_middle_name' => 'nullable|string|max:255',
                'husband_last_name' => 'required|string|max:255',
                'husband_birthdate' => 'required|date',
                'husband_birthplace' => 'required|string|max:255',
                'husband_sex' => 'required|in:Male,Female',
                'husband_citizenship' => 'required|string|max:255',
                'husband_religion' => 'nullable|string|max:255',
                'husband_civil_status' => 'required|in:Single,Widowed,Divorced,Annulled',
                'husband_occupation' => 'nullable|string|max:255',
                'husband_address' => 'required|string',

                // Husband Parents
                'husband_father_name' => 'required|string|max:255',
                'husband_father_citizenship' => 'required|string|max:255',
                'husband_mother_name' => 'required|string|max:255',
                'husband_mother_citizenship' => 'required|string|max:255',

                // Husband Consent
                'husband_consent_giver' => 'nullable|string|max:255',
                'husband_consent_relationship' => 'nullable|string|max:255',
                'husband_consent_address' => 'nullable|string|max:255',

                // Wife Information
                'wife_first_name' => 'required|string|max:255',
                'wife_middle_name' => 'nullable|string|max:255',
                'wife_last_name' => 'required|string|max:255',
                'wife_birthdate' => 'required|date',
                'wife_birthplace' => 'required|string|max:255',
                'wife_sex' => 'required|in:Male,Female',
                'wife_citizenship' => 'required|string|max:255',
                'wife_religion' => 'nullable|string|max:255',
                'wife_civil_status' => 'required|in:Single,Widowed,Divorced,Annulled',
                'wife_occupation' => 'nullable|string|max:255',
                'wife_address' => 'required|string',

                // Wife Parents
                'wife_father_name' => 'required|string|max:255',
                'wife_father_citizenship' => 'required|string|max:255',
                'wife_mother_name' => 'required|string|max:255',
                'wife_mother_citizenship' => 'required|string|max:255',

                // Wife Consent
                'wife_consent_giver' => 'nullable|string|max:255',
                'wife_consent_relationship' => 'nullable|string|max:255',
                'wife_consent_address' => 'nullable|string|max:255',

                // Ceremony Details
                'officiating_officer' => 'required|string|max:255',
                'officiant_title' => 'nullable|string|max:255',
                'officiant_license' => 'nullable|string|max:255',

                // Legal Basis
                'legal_basis' => 'nullable|string|max:255',
                'legal_basis_article' => 'nullable|string|max:255',

                // Witnesses
                'witness1_name' => 'required|string|max:255',
                'witness1_address' => 'required|string|max:255',
                'witness1_relationship' => 'nullable|string|max:255',
                'witness2_name' => 'required|string|max:255',
                'witness2_address' => 'required|string|max:255',
                'witness2_relationship' => 'nullable|string|max:255',

                // Additional
                'marriage_remarks' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the authenticated user
            $user = Auth::user();

            // Find the existing record
            $marriageRecord = MarriageRecord::active()->findOrFail($id);

            // Phase 2: Duplicate = names + date + place of marriage (excluding current record)
            $dupQuery = MarriageRecord::where('husband_first_name', $request->husband_first_name)
                ->where('husband_last_name', $request->husband_last_name)
                ->where('wife_first_name', $request->wife_first_name)
                ->where('wife_last_name', $request->wife_last_name)
                ->where('date_of_marriage', $request->date_of_marriage)
                ->where('id', '!=', $id)
                ->active();
            if ($request->filled('place_of_marriage')) {
                $dupQuery->where('place_of_marriage', $request->place_of_marriage);
            }
            $duplicateCheck = $dupQuery->first();

            if ($duplicateCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record found. Another record with the same couple, date, and place already exists.',
                    'is_duplicate' => true,
                    'existing_record' => $duplicateCheck
                ], 409);
            }

            // Update all fields - FIXED: Removed encoded_by from update since we don't change who encoded it
            $marriageRecord->update([
                // Basic Information
                'province' => $request->province,
                'city_municipality' => $request->city_municipality,
                'date_of_marriage' => $request->date_of_marriage,
                'time_of_marriage' => $request->time_of_marriage,
                'place_of_marriage' => $request->place_of_marriage,
                'marriage_type' => $request->marriage_type,
                'license_number' => $request->license_number,
                'license_date' => $request->license_date,
                'license_place' => $request->license_place,
                'property_regime' => $request->property_regime,

                // Husband Information
                'husband_first_name' => $request->husband_first_name,
                'husband_middle_name' => $request->husband_middle_name,
                'husband_last_name' => $request->husband_last_name,
                'husband_birthdate' => $request->husband_birthdate,
                'husband_birthplace' => $request->husband_birthplace,
                'husband_sex' => $request->husband_sex,
                'husband_citizenship' => $request->husband_citizenship,
                'husband_religion' => $request->husband_religion,
                'husband_civil_status' => $request->husband_civil_status,
                'husband_occupation' => $request->husband_occupation,
                'husband_address' => $request->husband_address,

                // Husband Parents
                'husband_father_name' => $request->husband_father_name,
                'husband_father_citizenship' => $request->husband_father_citizenship,
                'husband_mother_name' => $request->husband_mother_name,
                'husband_mother_citizenship' => $request->husband_mother_citizenship,

                // Husband Consent
                'husband_consent_giver' => $request->husband_consent_giver,
                'husband_consent_relationship' => $request->husband_consent_relationship,
                'husband_consent_address' => $request->husband_consent_address,

                // Wife Information
                'wife_first_name' => $request->wife_first_name,
                'wife_middle_name' => $request->wife_middle_name,
                'wife_last_name' => $request->wife_last_name,
                'wife_birthdate' => $request->wife_birthdate,
                'wife_birthplace' => $request->wife_birthplace,
                'wife_sex' => $request->wife_sex,
                'wife_citizenship' => $request->wife_citizenship,
                'wife_religion' => $request->wife_religion,
                'wife_civil_status' => $request->wife_civil_status,
                'wife_occupation' => $request->wife_occupation,
                'wife_address' => $request->wife_address,

                // Wife Parents
                'wife_father_name' => $request->wife_father_name,
                'wife_father_citizenship' => $request->wife_father_citizenship,
                'wife_mother_name' => $request->wife_mother_name,
                'wife_mother_citizenship' => $request->wife_mother_citizenship,

                // Wife Consent
                'wife_consent_giver' => $request->wife_consent_giver,
                'wife_consent_relationship' => $request->wife_consent_relationship,
                'wife_consent_address' => $request->wife_consent_address,

                // Ceremony Details
                'officiating_officer' => $request->officiating_officer,
                'officiant_title' => $request->officiant_title,
                'officiant_license' => $request->officiant_license,

                // Legal Basis
                'legal_basis' => $request->legal_basis,
                'legal_basis_article' => $request->legal_basis_article,

                // Witnesses
                'witness1_name' => $request->witness1_name,
                'witness1_address' => $request->witness1_address,
                'witness1_relationship' => $request->witness1_relationship,
                'witness2_name' => $request->witness2_name,
                'witness2_address' => $request->witness2_address,
                'witness2_relationship' => $request->witness2_relationship,

                // Additional
                'marriage_remarks' => $request->marriage_remarks,
            ]);

            DB::commit();

            // Reload relationships
            $marriageRecord->load([
                'encodedByStaff',
                'encodedByAdmin'
            ]);

            $transformedRecord = $this->transformMarriageRecord($marriageRecord);

            return response()->json([
                'success' => true,
                'message' => 'Marriage record updated successfully!',
                'data' => $transformedRecord
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update marriage record: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $record = MarriageRecord::with([
                'encodedByStaff',
                'encodedByAdmin'
            ])->active()->findOrFail($id);

            $transformedRecord = $this->transformMarriageRecord($record);

            return response()->json([
                'success' => true,
                'data' => $transformedRecord,
                'message' => 'Marriage record retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Marriage record not found'
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $record = MarriageRecord::active()->findOrFail($id);
            $record->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Marriage record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete marriage record'
            ], 500);
        }
    }

    public function statistics()
    {
        try {
            $totalRecords = MarriageRecord::active()->count();
            $thisMonth = MarriageRecord::active()
                ->whereYear('created_at', date('Y'))
                ->whereMonth('created_at', date('m'))
                ->count();
            $thisYear = MarriageRecord::active()
                ->whereYear('created_at', date('Y'))
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_records' => $totalRecords,
                    'this_month' => $thisMonth,
                    'this_year' => $thisYear,
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

        $lastRecord = MarriageRecord::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastRecord ?
            (int) substr($lastRecord->registry_number, -5) + 1 : 1;

        return "MR-{$year}-" . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    private function transformMarriageRecord($record)
    {
        $encoderInfo = $this->getEncoderInfo($record);

        return [
            'id' => $record->id,
            'registry_number' => $record->registry_number,

            // Basic Information
            'province' => $record->province,
            'city_municipality' => $record->city_municipality,
            'date_of_marriage' => $record->date_of_marriage,
            'time_of_marriage' => $record->time_of_marriage,
            'place_of_marriage' => $record->place_of_marriage,
            'marriage_type' => $record->marriage_type,
            'license_number' => $record->license_number,
            'license_date' => $record->license_date,
            'license_place' => $record->license_place,
            'property_regime' => $record->property_regime,

            // Husband Information
            'husband_first_name' => $record->husband_first_name,
            'husband_middle_name' => $record->husband_middle_name,
            'husband_last_name' => $record->husband_last_name,
            'husband_birthdate' => $record->husband_birthdate,
            'husband_birthplace' => $record->husband_birthplace,
            'husband_sex' => $record->husband_sex,
            'husband_citizenship' => $record->husband_citizenship,
            'husband_religion' => $record->husband_religion,
            'husband_civil_status' => $record->husband_civil_status,
            'husband_occupation' => $record->husband_occupation,
            'husband_address' => $record->husband_address,

            // Husband Parents
            'husband_father_name' => $record->husband_father_name,
            'husband_father_citizenship' => $record->husband_father_citizenship,
            'husband_mother_name' => $record->husband_mother_name,
            'husband_mother_citizenship' => $record->husband_mother_citizenship,

            // Husband Consent
            'husband_consent_giver' => $record->husband_consent_giver,
            'husband_consent_relationship' => $record->husband_consent_relationship,
            'husband_consent_address' => $record->husband_consent_address,

            // Wife Information
            'wife_first_name' => $record->wife_first_name,
            'wife_middle_name' => $record->wife_middle_name,
            'wife_last_name' => $record->wife_last_name,
            'wife_birthdate' => $record->wife_birthdate,
            'wife_birthplace' => $record->wife_birthplace,
            'wife_sex' => $record->wife_sex,
            'wife_citizenship' => $record->wife_citizenship,
            'wife_religion' => $record->wife_religion,
            'wife_civil_status' => $record->wife_civil_status,
            'wife_occupation' => $record->wife_occupation,
            'wife_address' => $record->wife_address,

            // Wife Parents
            'wife_father_name' => $record->wife_father_name,
            'wife_father_citizenship' => $record->wife_father_citizenship,
            'wife_mother_name' => $record->wife_mother_name,
            'wife_mother_citizenship' => $record->wife_mother_citizenship,

            // Wife Consent
            'wife_consent_giver' => $record->wife_consent_giver,
            'wife_consent_relationship' => $record->wife_consent_relationship,
            'wife_consent_address' => $record->wife_consent_address,

            // Ceremony Details
            'officiating_officer' => $record->officiating_officer,
            'officiant_title' => $record->officiant_title,
            'officiant_license' => $record->officiant_license,

            // Legal Basis
            'legal_basis' => $record->legal_basis,
            'legal_basis_article' => $record->legal_basis_article,

            // Witnesses
            'witness1_name' => $record->witness1_name,
            'witness1_address' => $record->witness1_address,
            'witness1_relationship' => $record->witness1_relationship,
            'witness2_name' => $record->witness2_name,
            'witness2_address' => $record->witness2_address,
            'witness2_relationship' => $record->witness2_relationship,

            // Additional
            'marriage_remarks' => $record->marriage_remarks,

            // System
            'date_registered' => $record->date_registered,
            'is_active' => $record->is_active,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,

            // Computed fields
            'encoded_by' => $encoderInfo,
            'encoder_name' => $record->encoder_name,
            'encoder_type' => $record->encoder_type,
            'husband_full_name' => $record->husband_full_name,
            'wife_full_name' => $record->wife_full_name,
            'couple_names' => $record->couple_names,
            'formatted_date_of_marriage' => $record->formatted_date_of_marriage,
            'formatted_time_of_marriage' => $record->formatted_time_of_marriage,
        ];
    }

    // In MarriageRecordController - Update the getEncoderInfo method

    private function getEncoderInfo($record)
    {
        $encoderInfo = [
            'id' => null,
            'full_name' => 'System',
            'user_type' => 'System',
            'email' => null,
            'position' => 'System Account'
        ];

        // Load relationships if not already loaded
        if (!$record->relationLoaded('encodedByAdmin')) {
            $record->load('encodedByAdmin');
        }
        if (!$record->relationLoaded('encodedByStaff')) {
            $record->load('encodedByStaff');
        }

        // Check admin first since admin IDs might overlap with staff IDs
        if ($record->encodedByAdmin) {
            $encoderInfo = [
                'id' => $record->encodedByAdmin->id,
                'full_name' => $record->encodedByAdmin->full_name ?? 'Unknown Admin',
                'user_type' => 'Admin',
                'email' => $record->encodedByAdmin->email,
                'position' => $record->encodedByAdmin->position ?? 'System Administrator'
            ];
        } elseif ($record->encodedByStaff) {
            $encoderInfo = [
                'id' => $record->encodedByStaff->id,
                'full_name' => $record->encodedByStaff->full_name ?? 'Unknown Staff',
                'user_type' => 'Staff',
                'email' => $record->encodedByStaff->email,
                'position' => 'Registry Staff'
            ];
        }

        return $encoderInfo;
    }
}
