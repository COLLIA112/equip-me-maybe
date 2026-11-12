<?php

namespace App\Http\Controllers\Web;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    /** My Reservations (student and faculty). */
    public function index(Request $request)
    {
        $this->authorize('create', Reservation::class);

        $reservations = $request->user()->reservations()
            ->with('equipment.lab')
            ->latest('starts_at')
            ->paginate(10);

        return view('reservations.index', compact('reservations'));
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
        );

        return redirect()->route('reservations.index')->with('status', 'Reservation requested. Waiting for approval.');
    }

    public function cancel(Reservation $reservation): RedirectResponse
    {
        $this->authorize('cancel', $reservation);

        $this->service->cancel($reservation);

        return back()->with('status', 'Reservation cancelled.');
    }

    // ---------- lab manager screens ----------

    /** Reservation Queue, with the compliance check shown for each row. */
    public function queue(Request $request)
    {
        abort_unless($request->user()->hasRole(Role::LabManager), 403);

        $reservations = Reservation::query()
            ->with(['equipment', 'user'])
            ->whereHas('equipment', fn ($q) => $q->where('lab_id', $request->user()->lab_id))
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved, ReservationStatus::CheckedOut])
            ->orderBy('starts_at')
            ->paginate(15);

        $compliance = $this->service->complianceMap($reservations->getCollection());

        return view('reservations.queue', compact('reservations', 'compliance'));
    }

    public function approve(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('manage', $reservation);

        $this->service->approve($reservation, $request->user());

        return back()->with('status', 'Reservation approved.');
    }

    public function reject(Reservation $reservation): RedirectResponse
    {
        $this->authorize('manage', $reservation);

        $this->service->reject($reservation);

        return back()->with('status', 'Reservation rejected.');
    }

    public function handover(Reservation $reservation): RedirectResponse
    {
        $this->authorize('manage', $reservation);

        $this->service->handOver($reservation);

        return back()->with('status', 'Item handed over.');
    }

    public function return(Reservation $reservation): RedirectResponse
    {
        $this->authorize('manage', $reservation);

        $this->service->markReturned($reservation);

        return back()->with('status', 'Item returned and available again.');
    }
}
