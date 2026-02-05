<?php
// app/Http/Controllers/BirthRecordController.php - COMPLETE VERSION
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
use Illuminate\Support\Facades\Auth;

class BirthRecordController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = BirthRecord::with([
                'mother',
                'father',
                'parentsMarriage',
                'attendant',
                'informant',
                'encodedByStaff',
                'encodedByAdmin'
            ])->active();

            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->search($searchTerm);
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->where('date_of_birth', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->where('date_of_birth', '<=', $request->date_to);
            }

            // Phase 2: Place filter (not name only)
            if ($request->filled('place_of_birth')) {
                $query->where('place_of_birth', 'like', '%' . $request->place_of_birth . '%');
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 1000);

            $records = $query->paginate($perPage);

            $transformedRecords = $records->getCollection()->map(function ($record) {
                return $this->transformBirthRecord($record);
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
                'message' => 'Failed to retrieve birth records: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkDuplicate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'child_first_name' => 'required|string',
                'child_last_name' => 'required|string',
                'date_of_birth' => 'required|date',
                'place_of_birth' => 'nullable|string',
                'exclude_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Phase 2: Exact duplicate = name + date + place of birth (not name only)
            $query = BirthRecord::where('child_first_name', $request->child_first_name)
                ->where('child_last_name', $request->child_last_name)
                ->where('date_of_birth', $request->date_of_birth)
                ->active();
            if ($request->filled('place_of_birth')) {
                $query->where('place_of_birth', $request->place_of_birth);
            }

            // Exclude specific ID if provided (for update operations)
            if ($request->has('exclude_id') && $request->exclude_id) {
                $query->where('id', '!=', $request->exclude_id);
            }

            $exactDuplicate = $query->first();

            $similarRecords = BirthRecord::where(function ($query) use ($request) {
                $query->where('child_first_name', $request->child_first_name)
                    ->where('child_last_name', 'like', "%{$request->child_last_name}%");
            })
                ->orWhere(function ($query) use ($request) {
                    $query->where('child_first_name', 'like', "%{$request->child_first_name}%")
                        ->where('child_last_name', $request->child_last_name);
                })
                ->orWhere(function ($query) use ($request) {
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
                    'date_of_birth' => $request->date_of_birth,
                    'place_of_birth' => $request->place_of_birth,
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
                'birth_address_province' => 'nullable|string|max:255',
                'type_of_birth' => 'required|in:Single,Twin,Triplet,Quadruplet,Other',
                'multiple_birth_order' => 'nullable|in:First,Second,Third,Fourth,Fifth',
                'birth_order' => 'required|integer|min:1',
                'birth_weight' => 'nullable|numeric|min:0.5|max:10',
                'birth_notes' => 'nullable|string',

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

                'marriage_date' => 'nullable|date',
                'marriage_place_city' => 'nullable|string|max:255',
                'marriage_place_province' => 'nullable|string|max:255',
                'marriage_place_country' => 'nullable|string|max:255',

                'attendant_type' => 'required|in:Physician,Nurse,Midwife,Hilot,Other',
                'attendant_name' => 'required|string|max:255',
                'attendant_license' => 'nullable|string|max:255',
                'attendant_certification' => 'required|string',
                'attendant_address' => 'required|string|max:255',
                'attendant_title' => 'required|string|max:255',

                'informant_first_name' => 'required|string|max:255',
                'informant_middle_name' => 'nullable|string|max:255',
                'informant_last_name' => 'required|string|max:255',
                'informant_relationship' => 'required|string|max:255',
                'informant_address' => 'required|string|max:255',
                'informant_certification_accepted' => 'required|boolean',

                // Phase 3: Late registration, legitimacy, name change
                'is_late_registration' => 'nullable|boolean',
                'legitimacy_status' => 'nullable|in:Legitimate,Illegitimate',
                'father_acknowledgment' => 'nullable|string|max:1000',
                'name_changed' => 'nullable|boolean',
                'current_first_name' => 'required_if:name_changed,true|nullable|string|max:255',
                'current_middle_name' => 'nullable|string|max:255',
                'current_last_name' => 'required_if:name_changed,true|nullable|string|max:255',
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

            // Phase 2: Duplicate = name + date + place of birth (not name only)
            $dupQuery = BirthRecord::where('child_first_name', $request->child_first_name)
                ->where('child_last_name', $request->child_last_name)
                ->where('date_of_birth', $request->date_of_birth)
                ->active();
            if ($request->filled('place_of_birth')) {
                $dupQuery->where('place_of_birth', $request->place_of_birth);
            }
            $duplicateCheck = $dupQuery->first();

            if ($duplicateCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record found. A record with the same child name, date of birth, and place of birth already exists.',
                    'is_duplicate' => true,
                    'existing_record' => $duplicateCheck
                ], 409);
            }

            $registryNumber = $this->generateRegistryNumber();

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
                'encoded_by' => $encodedBy,
                'is_late_registration' => (bool) ($request->is_late_registration ?? false),
                'legitimacy_status' => $request->legitimacy_status ?? 'Legitimate',
                'father_acknowledgment' => $request->father_acknowledgment,
                'name_changed' => (bool) ($request->name_changed ?? false),
                'current_first_name' => $request->current_first_name,
                'current_middle_name' => $request->current_middle_name,
                'current_last_name' => $request->current_last_name,
            ]);

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

            if ($request->marriage_date || $request->marriage_place_city) {
                ParentsMarriage::create([
                    'birth_record_id' => $birthRecord->id,
                    'marriage_date' => $request->marriage_date,
                    'marriage_place_city' => $request->marriage_place_city,
                    'marriage_place_province' => $request->marriage_place_province,
                    'marriage_place_country' => $request->marriage_place_country,
                ]);
            }

            BirthAttendant::create([
                'birth_record_id' => $birthRecord->id,
                'attendant_type' => $request->attendant_type,
                'attendant_name' => $request->attendant_name,
                'attendant_license' => $request->attendant_license,
                'attendant_certification' => $request->attendant_certification,
                'attendant_address' => $request->attendant_address,
                'attendant_title' => $request->attendant_title,
            ]);

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

            $birthRecord->load([
                'mother',
                'father',
                'parentsMarriage',
                'attendant',
                'informant',
                'encodedByStaff',
                'encodedByAdmin'
            ]);

            $transformedRecord = $this->transformBirthRecord($birthRecord);

            return response()->json([
                'success' => true,
                'message' => 'Birth record saved successfully!',
                'data' => $transformedRecord,
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

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
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
                'birth_address_province' => 'nullable|string|max:255',
                'type_of_birth' => 'required|in:Single,Twin,Triplet,Quadruplet,Other',
                'multiple_birth_order' => 'nullable|in:First,Second,Third,Fourth,Fifth',
                'birth_order' => 'required|integer|min:1',
                'birth_weight' => 'nullable|numeric|min:0.5|max:10',
                'birth_notes' => 'nullable|string',

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

                'marriage_date' => 'nullable|date',
                'marriage_place_city' => 'nullable|string|max:255',
                'marriage_place_province' => 'nullable|string|max:255',
                'marriage_place_country' => 'nullable|string|max:255',

                'attendant_type' => 'required|in:Physician,Nurse,Midwife,Hilot,Other',
                'attendant_name' => 'required|string|max:255',
                'attendant_license' => 'nullable|string|max:255',
                'attendant_certification' => 'required|string',
                'attendant_address' => 'required|string|max:255',
                'attendant_title' => 'required|string|max:255',

                'informant_first_name' => 'required|string|max:255',
                'informant_middle_name' => 'nullable|string|max:255',
                'informant_last_name' => 'required|string|max:255',
                'informant_relationship' => 'required|string|max:255',
                'informant_address' => 'required|string|max:255',
                'informant_certification_accepted' => 'required|boolean',

                // Phase 3
                'is_late_registration' => 'nullable|boolean',
                'legitimacy_status' => 'nullable|in:Legitimate,Illegitimate',
                'father_acknowledgment' => 'nullable|string|max:1000',
                'name_changed' => 'nullable|boolean',
                'current_first_name' => 'required_if:name_changed,true|nullable|string|max:255',
                'current_middle_name' => 'nullable|string|max:255',
                'current_last_name' => 'required_if:name_changed,true|nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $birthRecord = BirthRecord::active()->findOrFail($id);

            // Phase 2: Duplicate = name + date + place of birth (excluding current record)
            $dupQuery = BirthRecord::where('child_first_name', $request->child_first_name)
                ->where('child_last_name', $request->child_last_name)
                ->where('date_of_birth', $request->date_of_birth)
                ->where('id', '!=', $id)
                ->active();
            if ($request->filled('place_of_birth')) {
                $dupQuery->where('place_of_birth', $request->place_of_birth);
            }
            $duplicateCheck = $dupQuery->first();

            if ($duplicateCheck) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record found. Another record with the same child name, date of birth, and place of birth already exists.',
                    'is_duplicate' => true,
                    'existing_record' => $duplicateCheck
                ], 409);
            }

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
                'is_late_registration' => (bool) ($request->is_late_registration ?? false),
                'legitimacy_status' => $request->legitimacy_status ?? 'Legitimate',
                'father_acknowledgment' => $request->father_acknowledgment,
                'name_changed' => (bool) ($request->name_changed ?? false),
                'current_first_name' => $request->current_first_name,
                'current_middle_name' => $request->current_middle_name,
                'current_last_name' => $request->current_last_name,
            ]);

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

            if ($birthRecord->parentsMarriage) {
                if ($request->marriage_date || $request->marriage_place_city) {
                    $birthRecord->parentsMarriage->update([
                        'marriage_date' => $request->marriage_date,
                        'marriage_place_city' => $request->marriage_place_city,
                        'marriage_place_province' => $request->marriage_place_province,
                        'marriage_place_country' => $request->marriage_place_country,
                    ]);
                } else {
                    $birthRecord->parentsMarriage->delete();
                }
            } else if ($request->marriage_date || $request->marriage_place_city) {
                ParentsMarriage::create([
                    'birth_record_id' => $birthRecord->id,
                    'marriage_date' => $request->marriage_date,
                    'marriage_place_city' => $request->marriage_place_city,
                    'marriage_place_province' => $request->marriage_place_province,
                    'marriage_place_country' => $request->marriage_place_country,
                ]);
            }

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

            // Reload relationships - USE THE CORRECT RELATIONSHIP NAMES
            $birthRecord->load([
                'mother',
                'father',
                'parentsMarriage',
                'attendant',
                'informant',
                'encodedByStaff',  // Use encodedByStaff instead of encodedBy
                'encodedByAdmin'   // Use encodedByAdmin
            ]);


            $transformedRecord = $this->transformBirthRecord($birthRecord);

            return response()->json([
                'success' => true,
                'message' => 'Birth record updated successfully!',
                'data' => $transformedRecord  // Return the transformed record with encoder info
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update birth record: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $record = BirthRecord::with([
                'mother',
                'father',
                'parentsMarriage',
                'attendant',
                'informant',
                'encodedByStaff',
                'encodedByAdmin'
            ])->active()->findOrFail($id);

            $transformedRecord = $this->transformBirthRecord($record);

            return response()->json([
                'success' => true,
                'data' => $transformedRecord,
                'message' => 'Birth record retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Birth record not found'
            ], 404);
        }
    }

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

    private function transformBirthRecord($record)
    {
        $encoderInfo = $this->getEncoderInfo($record);

        return [
            'id' => $record->id,
            'registry_number' => $record->registry_number,
            'child_first_name' => $record->child_first_name,
            'child_middle_name' => $record->child_middle_name,
            'child_last_name' => $record->child_last_name,
            'sex' => $record->sex,
            'date_of_birth' => $record->date_of_birth,
            'time_of_birth' => $record->time_of_birth,
            'place_of_birth' => $record->place_of_birth,
            'birth_address_house' => $record->birth_address_house,
            'birth_address_barangay' => $record->birth_address_barangay,
            'birth_address_city' => $record->birth_address_city,
            'birth_address_province' => $record->birth_address_province,
            'type_of_birth' => $record->type_of_birth,
            'multiple_birth_order' => $record->multiple_birth_order,
            'birth_order' => $record->birth_order,
            'birth_weight' => $record->birth_weight,
            'birth_notes' => $record->birth_notes,
            'date_registered' => $record->date_registered,
            'is_active' => $record->is_active,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,

            'is_late_registration' => (bool) ($record->is_late_registration ?? false),
            'legitimacy_status' => $record->legitimacy_status ?? 'Legitimate',
            'father_acknowledgment' => $record->father_acknowledgment,
            'name_changed' => (bool) ($record->name_changed ?? false),
            'current_first_name' => $record->current_first_name,
            'current_middle_name' => $record->current_middle_name,
            'current_last_name' => $record->current_last_name,

            'mother' => $record->mother,
            'father' => $record->father,
            'parents_marriage' => $record->parentsMarriage,
            'attendant' => $record->attendant,
            'informant' => $record->informant,

            'encoded_by' => $encoderInfo,
            'encoder_name' => $record->encoder_name,
            'encoder_type' => $record->encoder_type,

            'full_name' => $record->full_name,
            'display_name' => $record->display_name,
            'birth_address' => $record->birth_address,
            'formatted_date_of_birth' => $record->formatted_date_of_birth,
            'formatted_time_of_birth' => $record->formatted_time_of_birth,
        ];
    }

    /**
     * Get encoder information based on user type - FIXED
     */
    private function getEncoderInfo($record)
    {
        // Default system info
        $encoderInfo = [
            'id' => null,
            'full_name' => 'System',
            'user_type' => 'System',
            'email' => null,
            'position' => 'System Account'
        ];

        // FIX: Check Admin relationship FIRST
        if ($record->relationLoaded('encodedByAdmin') && $record->encodedByAdmin) {
            $encoderInfo = [
                'id' => $record->encodedByAdmin->id,
                'full_name' => $record->encodedByAdmin->full_name,
                'user_type' => 'Admin',
                'email' => $record->encodedByAdmin->email,
                'position' => $record->encodedByAdmin->position ?? 'System Administrator'
            ];
        }
        // Then check Staff relationship
        elseif ($record->relationLoaded('encodedBy') && $record->encodedBy) {
            $encoderInfo = [
                'id' => $record->encodedBy->id,
                'full_name' => $record->encodedBy->full_name,
                'user_type' => 'Staff',
                'email' => $record->encodedBy->email,
                'position' => 'Registry Staff'
            ];
        }
        // Fallback to accessor methods
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

    // Add this method to your BirthRecordController
    public function statistics()
    {
        try {
            $totalRecords = BirthRecord::active()->count();
            $maleCount = BirthRecord::active()->where('sex', 'Male')->count();
            $femaleCount = BirthRecord::active()->where('sex', 'Female')->count();
            $thisMonth = BirthRecord::active()
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
}
