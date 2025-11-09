<?php
// app/Http/Controllers/BirthRecordController.php
namespace App\Http\Controllers;

use App\Models\BirthRecord;
use App\Models\ParentsInformation;
use App\Models\ParentsMarriage;
use App\Models\BirthAttendant;
use App\Models\Informant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BirthRecordController extends Controller
{
    // In BirthRecordController.php - Update index method to support larger data fetch
    public function index(Request $request)
    {
        try {
            $query = BirthRecord::with(['mother', 'father', 'parentsMarriage', 'attendant', 'informant', 'encodedBy'])
                ->active();

            // Search functionality (optional - can be removed if using only client-side search)
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->search($searchTerm);
            }

            // Filter by date range (optional - can be removed if using only client-side filtering)
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->where('date_of_birth', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->where('date_of_birth', '<=', $request->date_to);
            }

            // Sort by latest first
            $query->orderBy('created_at', 'desc');

            // Get per_page parameter with a higher default for initial load
            $perPage = $request->get('per_page', 1000); // Increased default for client-side filtering

            $records = $query->paginate($perPage);

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
                'message' => 'Failed to retrieve birth records: ' . $e->getMessage()
            ], 500);
        }
    }

    // In BirthRecordController.php - Enhanced checkDuplicate method
    public function checkDuplicate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'child_first_name' => 'required|string',
                'child_last_name' => 'required|string',
                'date_of_birth' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Exact duplicate check
            $exactDuplicate = BirthRecord::where('child_first_name', $request->child_first_name)
                ->where('child_last_name', $request->child_last_name)
                ->where('date_of_birth', $request->date_of_birth)
                ->active()
                ->first();

            // Fuzzy search for similar records
            $similarRecords = BirthRecord::where(function ($query) use ($request) {
                // Same first name, similar last name
                $query->where('child_first_name', $request->child_first_name)
                    ->where('child_last_name', 'like', "%{$request->child_last_name}%");
            })
                ->orWhere(function ($query) use ($request) {
                    // Similar first name, same last name
                    $query->where('child_first_name', 'like', "%{$request->child_first_name}%")
                        ->where('child_last_name', $request->child_last_name);
                })
                ->orWhere(function ($query) use ($request) {
                    // Same name, different birth date within 30 days
                    $query->where('child_first_name', $request->child_first_name)
                        ->where('child_last_name', $request->child_last_name)
                        ->whereDate('date_of_birth', '>=', Carbon::parse($request->date_of_birth)->subDays(30))
                        ->whereDate('date_of_birth', '<=', Carbon::parse($request->date_of_birth)->addDays(30));
                })
                ->active()
                ->limit(10)
                ->get(['id', 'registry_number', 'child_first_name', 'child_middle_name', 'child_last_name', 'date_of_birth', 'sex', 'place_of_birth']);

            return response()->json([
                'success' => true,
                'is_duplicate' => !is_null($exactDuplicate),
                'duplicate_record' => $exactDuplicate,
                'similar_records' => $similarRecords,
                'checked_fields' => [
                    'child_first_name' => $request->child_first_name,
                    'child_last_name' => $request->child_last_name,
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

    // Store new birth record
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                // Child Information
                'child_first_name' => 'required|string|max:255',
                'child_middle_name' => 'nullable|string|max:255',
                'child_last_name' => 'required|string|max:255',
                'sex' => 'required|in:Male,Female',
                'date_of_birth' => 'required|date',
                'time_of_birth' => 'nullable|date_format:H:i',
                'place_of_birth' => 'required|string|max:255',
                'birth_address_house' => 'nullable|string|max:255',
                'birth_address_barangay' => 'nullable|string|max:255',
                'birth_address_city' => 'required|string|max:255',
                'type_of_birth' => 'required|in:Single,Twin,Triplet,Quadruplet,Other',
                'multiple_birth_order' => 'nullable|in:First,Second,Third,Fourth,Fifth',
                'birth_order' => 'required|integer|min:1',
                'birth_weight' => 'nullable|numeric|min:0.5|max:10',
                'birth_notes' => 'nullable|string',

                // Mother Information
                'mother_first_name' => 'required|string|max:255',
                'mother_middle_name' => 'nullable|string|max:255',
                'mother_last_name' => 'required|string|max:255',
                'mother_citizenship' => 'required|string|max:255',
                'mother_religion' => 'nullable|string|max:255',
                'mother_occupation' => 'nullable|string|max:255',
                'mother_age_at_birth' => 'required|integer|min:15|max:60',
                'mother_children_born_alive' => 'required|integer|min:0',
                'mother_children_still_living' => 'required|integer|min:0',
                'mother_children_deceased' => 'required|integer|min:0',
                'mother_house_no' => 'nullable|string|max:255',
                'mother_barangay' => 'required|string|max:255',
                'mother_city' => 'required|string|max:255',
                'mother_province' => 'required|string|max:255',
                'mother_country' => 'required|string|max:255',

                // Father Information
                'father_first_name' => 'required|string|max:255',
                'father_middle_name' => 'nullable|string|max:255',
                'father_last_name' => 'required|string|max:255',
                'father_citizenship' => 'required|string|max:255',
                'father_religion' => 'nullable|string|max:255',
                'father_occupation' => 'nullable|string|max:255',
                'father_age_at_birth' => 'required|integer|min:15|max:80',
                'father_house_no' => 'nullable|string|max:255',
                'father_barangay' => 'required|string|max:255',
                'father_city' => 'required|string|max:255',
                'father_province' => 'required|string|max:255',
                'father_country' => 'required|string|max:255',

                // Parents Marriage
                'marriage_date' => 'nullable|date',
                'marriage_place_city' => 'nullable|string|max:255',
                'marriage_place_province' => 'nullable|string|max:255',
                'marriage_place_country' => 'nullable|string|max:255',

                // Attendant Information
                'attendant_type' => 'required|in:Physician,Nurse,Midwife,Hilot,Other',
                'attendant_name' => 'required|string|max:255',
                'attendant_license' => 'nullable|string|max:255',
                'attendant_certification' => 'required|string',
                'attendant_address' => 'required|string|max:255',
                'attendant_title' => 'required|string|max:255',

                // Informant Information
                'informant_first_name' => 'required|string|max:255',
                'informant_middle_name' => 'nullable|string|max:255',
                'informant_last_name' => 'required|string|max:255',
                'informant_relationship' => 'required|string|max:255',
                'informant_address' => 'required|string|max:255',
                'informant_certification_accepted' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicates
            $duplicateCheck = BirthRecord::where('child_first_name', $request->child_first_name)
                ->where('child_last_name', $request->child_last_name)
                ->where('date_of_birth', $request->date_of_birth)
                ->active()
                ->first();

            if ($duplicateCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record found. A record with the same child name and date of birth already exists.',
                    'is_duplicate' => true,
                    'existing_record' => $duplicateCheck
                ], 409);
            }

            // Generate registry number
            $registryNumber = $this->generateRegistryNumber();

            // Create birth record
            $birthRecord = BirthRecord::create([
                'registry_number' => $registryNumber,
                'child_first_name' => $request->child_first_name,
                'child_middle_name' => $request->child_middle_name,
                'child_last_name' => $request->child_last_name,
                'sex' => $request->sex,
                'date_of_birth' => $request->date_of_birth,
                'time_of_birth' => $request->time_of_birth,
                'place_of_birth' => $request->place_of_birth,
                'birth_address_house' => $request->birth_address_house,
                'birth_address_barangay' => $request->birth_address_barangay,
                'birth_address_city' => $request->birth_address_city,
                'birth_address_province' => $request->birth_address_province,
                'type_of_birth' => $request->type_of_birth,
                'multiple_birth_order' => $request->multiple_birth_order,
                'birth_order' => $request->birth_order,
                'birth_weight' => $request->birth_weight,
                'birth_notes' => $request->birth_notes,
                'date_registered' => now(),
                'encoded_by' => auth()->id(),
            ]);

            // Create mother information
            ParentsInformation::create([
                'birth_record_id' => $birthRecord->id,
                'parent_type' => 'Mother',
                'first_name' => $request->mother_first_name,
                'middle_name' => $request->mother_middle_name,
                'last_name' => $request->mother_last_name,
                'citizenship' => $request->mother_citizenship,
                'religion' => $request->mother_religion,
                'occupation' => $request->mother_occupation,
                'age_at_birth' => $request->mother_age_at_birth,
                'children_born_alive' => $request->mother_children_born_alive,
                'children_still_living' => $request->mother_children_still_living,
                'children_deceased' => $request->mother_children_deceased,
                'house_no' => $request->mother_house_no,
                'barangay' => $request->mother_barangay,
                'city' => $request->mother_city,
                'province' => $request->mother_province,
                'country' => $request->mother_country,
            ]);

            // Create father information
            ParentsInformation::create([
                'birth_record_id' => $birthRecord->id,
                'parent_type' => 'Father',
                'first_name' => $request->father_first_name,
                'middle_name' => $request->father_middle_name,
                'last_name' => $request->father_last_name,
                'citizenship' => $request->father_citizenship,
                'religion' => $request->father_religion,
                'occupation' => $request->father_occupation,
                'age_at_birth' => $request->father_age_at_birth,
                'house_no' => $request->father_house_no,
                'barangay' => $request->father_barangay,
                'city' => $request->father_city,
                'province' => $request->father_province,
                'country' => $request->father_country,
            ]);

            // Create parents marriage if provided
            if ($request->marriage_date || $request->marriage_place_city) {
                ParentsMarriage::create([
                    'birth_record_id' => $birthRecord->id,
                    'marriage_date' => $request->marriage_date,
                    'marriage_place_city' => $request->marriage_place_city,
                    'marriage_place_province' => $request->marriage_place_province,
                    'marriage_place_country' => $request->marriage_place_country,
                ]);
            }

            // Create attendant information
            BirthAttendant::create([
                'birth_record_id' => $birthRecord->id,
                'attendant_type' => $request->attendant_type,
                'attendant_name' => $request->attendant_name,
                'attendant_license' => $request->attendant_license,
                'attendant_certification' => $request->attendant_certification,
                'attendant_address' => $request->attendant_address,
                'attendant_title' => $request->attendant_title,
            ]);

            // Create informant information
            Informant::create([
                'birth_record_id' => $birthRecord->id,
                'first_name' => $request->informant_first_name,
                'middle_name' => $request->informant_middle_name,
                'last_name' => $request->informant_last_name,
                'relationship' => $request->informant_relationship,
                'address' => $request->informant_address,
                'certification_accepted' => $request->informant_certification_accepted,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Birth record saved successfully!',
                'data' => $birthRecord->load(['mother', 'father', 'parentsMarriage', 'attendant', 'informant']),
                'registry_number' => $registryNumber
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save birth record: ' . $e->getMessage()
            ], 500);
        }
    }

    // In BirthRecordController.php - Add update method
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                // Child Information
                'child_first_name' => 'required|string|max:255',
                'child_middle_name' => 'nullable|string|max:255',
                'child_last_name' => 'required|string|max:255',
                'sex' => 'required|in:Male,Female',
                'date_of_birth' => 'required|date',
                'time_of_birth' => 'nullable|date_format:H:i',
                'place_of_birth' => 'required|string|max:255',
                'birth_address_house' => 'nullable|string|max:255',
                'birth_address_barangay' => 'nullable|string|max:255',
                'birth_address_city' => 'required|string|max:255',
                'type_of_birth' => 'required|in:Single,Twin,Triplet,Quadruplet,Other',
                'multiple_birth_order' => 'nullable|in:First,Second,Third,Fourth,Fifth',
                'birth_order' => 'required|integer|min:1',
                'birth_weight' => 'nullable|numeric|min:0.5|max:10',
                'birth_notes' => 'nullable|string',

                // Mother Information
                'mother_first_name' => 'required|string|max:255',
                'mother_middle_name' => 'nullable|string|max:255',
                'mother_last_name' => 'required|string|max:255',
                'mother_citizenship' => 'required|string|max:255',
                'mother_religion' => 'nullable|string|max:255',
                'mother_occupation' => 'nullable|string|max:255',
                'mother_age_at_birth' => 'required|integer|min:15|max:60',
                'mother_children_born_alive' => 'required|integer|min:0',
                'mother_children_still_living' => 'required|integer|min:0',
                'mother_children_deceased' => 'required|integer|min:0',
                'mother_house_no' => 'nullable|string|max:255',
                'mother_barangay' => 'required|string|max:255',
                'mother_city' => 'required|string|max:255',
                'mother_province' => 'required|string|max:255',
                'mother_country' => 'required|string|max:255',

                // Father Information
                'father_first_name' => 'required|string|max:255',
                'father_middle_name' => 'nullable|string|max:255',
                'father_last_name' => 'required|string|max:255',
                'father_citizenship' => 'required|string|max:255',
                'father_religion' => 'nullable|string|max:255',
                'father_occupation' => 'nullable|string|max:255',
                'father_age_at_birth' => 'required|integer|min:15|max:80',
                'father_house_no' => 'nullable|string|max:255',
                'father_barangay' => 'required|string|max:255',
                'father_city' => 'required|string|max:255',
                'father_province' => 'required|string|max:255',
                'father_country' => 'required|string|max:255',

                // Parents Marriage
                'marriage_date' => 'nullable|date',
                'marriage_place_city' => 'nullable|string|max:255',
                'marriage_place_province' => 'nullable|string|max:255',
                'marriage_place_country' => 'nullable|string|max:255',

                // Attendant Information
                'attendant_type' => 'required|in:Physician,Nurse,Midwife,Hilot,Other',
                'attendant_name' => 'required|string|max:255',
                'attendant_license' => 'nullable|string|max:255',
                'attendant_certification' => 'required|string',
                'attendant_address' => 'required|string|max:255',
                'attendant_title' => 'required|string|max:255',

                // Informant Information
                'informant_first_name' => 'required|string|max:255',
                'informant_middle_name' => 'nullable|string|max:255',
                'informant_last_name' => 'required|string|max:255',
                'informant_relationship' => 'required|string|max:255',
                'informant_address' => 'required|string|max:255',
                'informant_certification_accepted' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the birth record
            $birthRecord = BirthRecord::active()->findOrFail($id);

            // Check for duplicates (excluding current record)
            $duplicateCheck = BirthRecord::where('child_first_name', $request->child_first_name)
                ->where('child_last_name', $request->child_last_name)
                ->where('date_of_birth', $request->date_of_birth)
                ->where('id', '!=', $id) // Exclude current record
                ->active()
                ->first();

            if ($duplicateCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record found. Another record with the same child name and date of birth already exists.',
                    'is_duplicate' => true,
                    'existing_record' => $duplicateCheck
                ], 409);
            }

            // Update birth record
            $birthRecord->update([
                'child_first_name' => $request->child_first_name,
                'child_middle_name' => $request->child_middle_name,
                'child_last_name' => $request->child_last_name,
                'sex' => $request->sex,
                'date_of_birth' => $request->date_of_birth,
                'time_of_birth' => $request->time_of_birth,
                'place_of_birth' => $request->place_of_birth,
                'birth_address_house' => $request->birth_address_house,
                'birth_address_barangay' => $request->birth_address_barangay,
                'birth_address_city' => $request->birth_address_city,
                'birth_address_province' => $request->birth_address_province,
                'type_of_birth' => $request->type_of_birth,
                'multiple_birth_order' => $request->multiple_birth_order,
                'birth_order' => $request->birth_order,
                'birth_weight' => $request->birth_weight,
                'birth_notes' => $request->birth_notes,
            ]);

            // Update mother information
            if ($birthRecord->mother) {
                $birthRecord->mother->update([
                    'first_name' => $request->mother_first_name,
                    'middle_name' => $request->mother_middle_name,
                    'last_name' => $request->mother_last_name,
                    'citizenship' => $request->mother_citizenship,
                    'religion' => $request->mother_religion,
                    'occupation' => $request->mother_occupation,
                    'age_at_birth' => $request->mother_age_at_birth,
                    'children_born_alive' => $request->mother_children_born_alive,
                    'children_still_living' => $request->mother_children_still_living,
                    'children_deceased' => $request->mother_children_deceased,
                    'house_no' => $request->mother_house_no,
                    'barangay' => $request->mother_barangay,
                    'city' => $request->mother_city,
                    'province' => $request->mother_province,
                    'country' => $request->mother_country,
                ]);
            }

            // Update father information
            if ($birthRecord->father) {
                $birthRecord->father->update([
                    'first_name' => $request->father_first_name,
                    'middle_name' => $request->father_middle_name,
                    'last_name' => $request->father_last_name,
                    'citizenship' => $request->father_citizenship,
                    'religion' => $request->father_religion,
                    'occupation' => $request->father_occupation,
                    'age_at_birth' => $request->father_age_at_birth,
                    'house_no' => $request->father_house_no,
                    'barangay' => $request->father_barangay,
                    'city' => $request->father_city,
                    'province' => $request->father_province,
                    'country' => $request->father_country,
                ]);
            }

            // Update parents marriage
            if ($birthRecord->parentsMarriage) {
                if ($request->marriage_date || $request->marriage_place_city) {
                    $birthRecord->parentsMarriage->update([
                        'marriage_date' => $request->marriage_date,
                        'marriage_place_city' => $request->marriage_place_city,
                        'marriage_place_province' => $request->marriage_place_province,
                        'marriage_place_country' => $request->marriage_place_country,
                    ]);
                } else {
                    // Delete marriage record if all fields are empty
                    $birthRecord->parentsMarriage->delete();
                }
            } else if ($request->marriage_date || $request->marriage_place_city) {
                // Create new marriage record
                ParentsMarriage::create([
                    'birth_record_id' => $birthRecord->id,
                    'marriage_date' => $request->marriage_date,
                    'marriage_place_city' => $request->marriage_place_city,
                    'marriage_place_province' => $request->marriage_place_province,
                    'marriage_place_country' => $request->marriage_place_country,
                ]);
            }

            // Update attendant
            if ($birthRecord->attendant) {
                $birthRecord->attendant->update([
                    'attendant_type' => $request->attendant_type,
                    'attendant_name' => $request->attendant_name,
                    'attendant_license' => $request->attendant_license,
                    'attendant_certification' => $request->attendant_certification,
                    'attendant_address' => $request->attendant_address,
                    'attendant_title' => $request->attendant_title,
                ]);
            }

            // Update informant
            if ($birthRecord->informant) {
                $birthRecord->informant->update([
                    'first_name' => $request->informant_first_name,
                    'middle_name' => $request->informant_middle_name,
                    'last_name' => $request->informant_last_name,
                    'relationship' => $request->informant_relationship,
                    'address' => $request->informant_address,
                    'certification_accepted' => $request->informant_certification_accepted,
                ]);
            }

            DB::commit();

            // Reload relationships
            $birthRecord->load(['mother', 'father', 'parentsMarriage', 'attendant', 'informant']);

            return response()->json([
                'success' => true,
                'message' => 'Birth record updated successfully!',
                'data' => $birthRecord
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update birth record: ' . $e->getMessage()
            ], 500);
        }
    }

    // Generate unique registry number
    private function generateRegistryNumber()
    {
        $year = date('Y');

        $lastRecord = BirthRecord::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastRecord ?
            (int) substr($lastRecord->registry_number, -5) + 1 : 1;

        return "BR-{$year}-" . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    // Get single birth record
    public function show($id)
    {
        try {
            $record = BirthRecord::with(['mother', 'father', 'parentsMarriage', 'attendant', 'informant', 'encodedBy'])
                ->active()
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $record,
                'message' => 'Birth record retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Birth record not found'
            ], 404);
        }
    }


    // Soft delete birth record
    public function destroy($id)
    {
        try {
            $record = BirthRecord::active()->findOrFail($id);
            $record->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Birth record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete birth record'
            ], 500);
        }
    }
}
