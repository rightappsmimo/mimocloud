<?php

namespace App\Http\Controllers;

use App\Models\SmsBlast;
use App\Models\SmsBlastRecipient;
use App\Models\M06;
use App\Services\SmsBlastService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmsBlastController extends Controller
{
    protected $smsBlastService;

    public function __construct(SmsBlastService $smsBlastService)
    {
        $this->smsBlastService = $smsBlastService;
    }

    /**
     * Display SMS blast history
     */
    public function index(Request $request)
    {
        $query = SmsBlast::query()->withCount('recipients')->latest();

        // Filters
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $blasts = $query->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'total' => SmsBlast::count(),
            'sent' => SmsBlast::sent()->count(),
            'scheduled' => SmsBlast::scheduled()->count(),
            'draft' => SmsBlast::draft()->count(),
            'failed' => SmsBlast::failed()->count(),
        ];

        return view('pages.admin-panel.sms-blast.index', compact('blasts', 'stats'));
    }

    /**
     * Show create SMS blast form
     */
    public function create()
    {
        $parents = M06::whereNotNull('mobileno')
            ->where('isparent', true)
            ->select('d_code as id', 'firstname', 'lastname', 'd_name as name', 'mobileno as mobile', DB::raw("'true' as isparent"))
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->firstname . ' ' . $p->lastname,
                    'mobile' => $p->mobile,
                    'type' => 'parent'
                ];
            });

        $guardians = M06::whereNotNull('mobileno')
            ->where('isguardian', true)
            ->select('d_code as id', 'firstname', 'lastname', 'd_name as name', 'mobileno as mobile', DB::raw("'true' as isguardian"))
            ->get()
            ->map(function ($g) {
                return [
                    'id' => $g->id,
                    'name' => $g->firstname . ' ' . $g->lastname,
                    'mobile' => $g->mobile,
                    'type' => 'guardian'
                ];
            });

        $templates = $this->smsBlastService->getDefaultTemplates();

        return view('pages.admin-panel.sms-blast.create', compact('parents', 'guardians', 'templates'));
    }

    /**
     * Store new SMS blast
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'string|exists:m06,d_code',
            'schedule' => 'nullable|boolean',
            'scheduled_at' => 'nullable|required_if:schedule,1|date|after:now',
        ]);

        DB::beginTransaction();
        try {
            $blast = SmsBlast::create([
                'title' => $validated['title'],
                'message' => $validated['message'],
                'status' => 'draft',
                'total_recipients' => 0,
                'sent_count' => 0,
                'failed_count' => 0,
                'cost_per_sms' => 1.50,
                'total_cost' => 0,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
            ]);

            if (!empty($validated['schedule']) && $validated['schedule'] == 1) {
                $result = $this->smsBlastService->scheduleBlast($blast, $validated['recipient_ids'], $validated['scheduled_at']);
            } else {
                $result = $this->smsBlastService->sendBlast($blast, $validated['recipient_ids']);
            }

            if ($result['success']) {
                DB::commit();
                return redirect()->route('admin.sms-blasts.index')
                    ->with('success', 'SMS blast ' . ($blast->status === 'scheduled' ? 'scheduled' : 'sent') . ' successfully!');
            } else {
                DB::rollBack();
                return back()->withInput()->with('error', 'Failed to send SMS blast: ' . ($result['message'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Show blast details
     */
    public function show(SmsBlast $smsBlast)
    {
        $smsBlast->load(['recipients' => function ($query) {
            $query->latest()->paginate(50);
        }]);

        return view('pages.admin-panel.sms-blast.details', compact('smsBlast'));
    }

    /**
     * Show templates page
     */
    public function templates()
    {
        $templates = $this->smsBlastService->getDefaultTemplates();
        return view('pages.admin-panel.sms-blast.templates', compact('templates'));
    }

    /**
     * Resend to failed recipients
     */
    public function resendFailed(SmsBlast $smsBlast)
    {
        try
        {
            $failedRecipients = $smsBlast->recipients()
                ->where('status', SmsBlastRecipient::STATUS_FAILED)
                ->get();

            if ($failedRecipients->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No failed recipients to resend.'
                ]);
            }

            $sent = 0;
            $failed = 0;

            foreach ($failedRecipients as $recipient) {
                $m06 = M06::where('d_code', $recipient->recipient_id)->first();
                if ($m06) {
                    $message = $this->smsBlastService->prepareMessage($smsBlast->message, $m06);
                    $result = SendSmsService::sendnowsms($m06->mobileno, $message);

                    if ($result['success']) {
                        $recipient->update([
                            'status' => SmsBlastRecipient::STATUS_SENT,
                            'sent_at' => Carbon::now(),
                            'error_message' => null,
                        ]);
                        $sent++;
                    } else {
                        $recipient->update([
                            'error_message' => $result['response'],
                        ]);
                        $failed++;
                    }
                }
            }

            // Update blast stats
            $smsBlast->update([
                'sent_count' => $smsBlast->sent_count + $sent,
                'failed_count' => $smsBlast->failed_count - $sent + $failed,
                'status' => ($failed > 0) ? SmsBlast::STATUS_FAILED : SmsBlast::STATUS_SENT,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Resend complete: $sent sent, $failed failed.",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete blast
     */
    public function destroy(SmsBlast $smsBlast)
    {
        $smsBlast->recipients()->delete();
        $smsBlast->delete();

        return back()->with('success', 'SMS blast deleted successfully.');
    }
}
