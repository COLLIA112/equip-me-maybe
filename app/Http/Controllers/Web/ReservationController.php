<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\CounterLookupRequest;
use App\Http\Requests\RejectReservationRequest;
use App\Http\Requests\ReservationBulkRequest;
use App\Http\Requests\ReservationFilterRequest;
use App\Http\Requests\ReturnReservationRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use App\Support\ReservationCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\RevokeReservationRequest;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    /** My Reservations (student and faculty). */
    public function index(ReservationFilterRequest $request)
    {
        $this->authorize('create', Reservation::class);
        $filters = $request->filters();
        $query = $request->user()->reservations()->with('equipment.lab')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['overdue'] ?? false, fn ($query) => $query->checkedOut()->where('ends_at', '<', now()));
        $this->sort($query->getQuery(), $filters, 'desc');
        $reservations = $query->paginate(10)->withQueryString();
        $activeFilters = filled($filters['status'] ?? null) || filled($filters['overdue'] ?? null);

        return view('reservations.index', compact('reservations', 'filters', 'activeFilters'));
    }

    public function show(Reservation $reservation)
    {
        $this->authorize('view', $reservation);

        $reservation->load(['equipment.lab', 'user', 'approver', 'handoverManager', 'returnManager', 'rejector', 'canceller']);

        return view('reservations.show', compact('reservation'));
    }

    public function create(Equipment $equipment)
    {
        $this->authorize('create', Reservation::class);

        $booked = $equipment->reservations()->blocking()->where('ends_at', '>', now())->orderBy('starts_at')->get(['starts_at', 'ends_at']);

        return view('reservations.create', compact('equipment', 'booked'));
    }

    public function store(StoreReservationRequest $request, Equipment $equipment): RedirectResponse
    {
        $this->service->request(
            $request->user(),
            $equipment,
            Carbon::parse($request->input('starts_at')),
            Carbon::parse($request->input('ends_at')),
            $request->boolean('is_class_session'),
        );

        return redirect()->route('reservations.index')->with('status', 'Reservation requested. Waiting for approval.');
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('cancel', $reservation);

        $this->service->cancel($reservation, $request->user());

        return back()->with('status', 'Reservation #'.$reservation->id.' cancelled.');
    }

    // ---------- lab manager screens ----------

    /** Reservation Queue, with the compliance check shown for each row. */
    public function queue(ReservationFilterRequest $request)
    {
        abort_unless($request->user()->hasRole(Role::LabManager), 403);
        $filters = $request->filters();
        $query = Reservation::query()
            ->with(['equipment', 'user'])
            ->whereHas('equipment', fn ($query) => $query->where('lab_id', $request->user()->lab_id))
            ->when($filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
                fn ($query) => $query->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved, ReservationStatus::CheckedOut]))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->whereHas('user', fn ($borrower) => $borrower
                ->where(fn ($match) => $match->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))))
            ->when($filters['overdue'] ?? false, fn ($query) => $query->checkedOut()->where('ends_at', '<', now()));
        $this->sort($query, $filters);
        $reservations = $query->paginate(15)->withQueryString();
        $missingDocuments = $this->service->missingDocumentMap($reservations->getCollection());
        $compliance = array_map(fn (array $titles) => $titles === [], $missingDocuments);
        $activeFilters = filled($filters['status'] ?? null) || filled($filters['q'] ?? null) || filled($filters['overdue'] ?? null);

        return view('reservations.queue', compact('reservations', 'compliance', 'missingDocuments', 'filters', 'activeFilters'));
    }

    public function approve(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('manage', $reservation);

        $this->service->approve($reservation, $request->user());

        return back()->with('status', 'Reservation #'.$reservation->id.' approved.');
    }

    public function reject(RejectReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('manage', $reservation);

        $this->service->reject($reservation, $request->validated('rejection_reason'), $request->user());

        return back()->with('status', 'Reservation #'.$reservation->id.' rejected.');
    }

    public function handover(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('manage', $reservation);

        $this->service->handOver($reservation, $request->user());

        return back()->with('status', 'Reservation #'.$reservation->id.': item handed over.');
    }

    public function return(ReturnReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('manage', $reservation);

        $this->service->markReturned($reservation, $request->user(), $request->validated('return_condition') ?? 'good', $request->validated('return_notes'));

        return back()->with('status', 'Reservation #'.$reservation->id.': item returned.');
    }

    public function again(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->canReserveAgain($request->user()), 403);
        $equipment = $reservation->equipment;
        $booked = $equipment->reservations()->blocking()->where('ends_at', '>', now())->orderBy('starts_at')->get(['starts_at', 'ends_at']);
        $start = now()->addDay()->startOfHour();
        $duration = max(60, min(86400, $reservation->starts_at->diffInSeconds($reservation->ends_at)));
        $prefill = ['starts_at' => $start->format('Y-m-d\TH:i'), 'ends_at' => $start->copy()->addSeconds($duration)->format('Y-m-d\TH:i')];

        return view('reservations.create', compact('equipment', 'booked', 'prefill'));
    }

    public function calendar(Reservation $reservation, ReservationCalendar $calendar)
    {
        $this->authorize('view', $reservation);
        abort_unless($reservation->status === ReservationStatus::Approved, 403);
        $reservation->load(['equipment.lab', 'user']);

        return response($calendar->render($reservation), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reservation-'.$reservation->id.'.ics"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function bulk(ReservationBulkRequest $request)
    {
        $rows = Reservation::query()->with('equipment')->whereKey($request->validated('reservation_ids'))->orderBy('id')->get();
        // Authorize every selected record before changing any record in the batch.
        foreach ($rows as $reservation) {
            $this->authorize('manage', $reservation);
        }
        $result = ['succeeded' => 0, 'failed' => 0, 'errors' => []];
        foreach ($rows as $reservation) {
            try {
                if ($request->validated('action') === 'approve') {
                    $this->service->approve($reservation, $request->user());
                } else {
                    $this->service->reject($reservation, $request->validated('rejection_reason'), $request->user());
                }
                $result['succeeded']++;
            } catch (ValidationException $exception) {
                $result['failed']++;
                $result['errors'][$reservation->id] = collect($exception->errors())->flatten()->all();
            }
        }
        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with('status', 'Bulk '.$request->validated('action').': '.$result['succeeded'].' succeeded, '.$result['failed'].' skipped.')->with('bulk_result', $result);
    }

    public function counter(CounterLookupRequest $request)
    {
        $code = $request->validated('code');
        $reservations = collect();
        if (filled($code)) {
            $reservations = Reservation::query()->with(['equipment', 'user'])
                ->whereHas('equipment', fn ($query) => $query->where('lab_id', $request->user()->lab_id))
                ->whereIn('status', [ReservationStatus::Approved, ReservationStatus::CheckedOut])
                ->when(ctype_digit(ltrim($code, '#')),
                    fn ($query) => $query->whereKey((int) ltrim($code, '#')),
                    fn ($query) => $query->whereHas('equipment', fn ($item) => $item->where('qr_code', strtoupper($code))))
                ->orderBy('starts_at')->limit(20)->get();
        }
        $missingDocuments = $this->service->missingDocumentMap($reservations);

        return view('reservations.counter', compact('reservations', 'code', 'missingDocuments'));
    }

    public function counterLookup(CounterLookupRequest $request): RedirectResponse
    {
        return redirect()->route('reservations.counter', ['code' => $request->validated('code')]);
    }

    private function sort(Builder $query, array $filters, string $defaultDirection = 'asc'): void
    {
        $column = match ($filters['sort'] ?? 'starts_at') {
            'equipment' => Equipment::query()->select('name')->whereColumn('equipment.id', 'reservations.equipment_id'),
            'borrower' => User::query()->select('name')->whereColumn('users.id', 'reservations.user_id'),
            'ends_at' => 'reservations.ends_at',
            'status' => 'reservations.status',
            default => 'reservations.starts_at',
        };
        $query->orderBy($column, $filters['direction'] ?? $defaultDirection)->orderBy('reservations.id', $filters['direction'] ?? $defaultDirection);
    }

    public function revoke(RevokeReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $this->service->cancel($reservation, $request->user(), $request->validated('cancellation_reason'));

        return back()->with('status', 'Reservation #'.$reservation->id.' cancelled and the borrower notified.');
    }
}
