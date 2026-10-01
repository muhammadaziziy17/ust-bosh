<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Support\DatabaseError;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('landing');
    }

    public function storeApplication(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\(\) ]{7,32}$/'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], $this->validationMessages());

        // Email va telefonni solishtirish uchun yagona formatga keltiramiz.
        $validated['email'] = strtolower(trim($validated['email']));
        $phoneNormalized = $this->normalizePhone($validated['phone']);

        if (strlen($phoneNormalized) < 7) {
            throw ValidationException::withMessages([
                'phone' => __('landing.validation.phone_regex'),
            ]);
        }

        $contactKey = $validated['email'].'|'.$phoneNormalized;

        // Dublikat tekshiruvi DB darajasida: contact_key unique index'i
        // parallel so'rovlarni ham ushlaydi. Rad etilgan ariza egasi qayta
        // ariza qoldirsa — eski ariza yangilanadi (qayta ochiladi).
        $isDuplicate = false;

        try {
            DB::transaction(function () use ($validated, $phoneNormalized, $contactKey, &$isDuplicate): void {
                // Faol dublikat: xuddi shu email YOKI telefon bilan pending/approved ariza
                $activeDuplicate = Application::query()
                    ->where(function ($query) use ($contactKey, $phoneNormalized, $validated): void {
                        $query->where('contact_key', $contactKey)
                            ->orWhere('phone_normalized', $phoneNormalized)
                            ->orWhere('email', $validated['email']);
                    })
                    ->whereIn('status', [Application::STATUS_PENDING, Application::STATUS_APPROVED])
                    ->lockForUpdate()
                    ->first();

                if ($activeDuplicate) {
                    $isDuplicate = true;

                    return;
                }

                // Rad etilgan ariza qayta topshirilsa — eski ariza yangilanadi (qayta ochiladi)
                $rejected = Application::query()
                    ->where('contact_key', $contactKey)
                    ->where('status', Application::STATUS_REJECTED)
                    ->lockForUpdate()
                    ->first();

                if ($rejected) {
                    $rejected->update([
                        'shop_name' => $validated['shop_name'],
                        'contact_name' => $validated['contact_name'],
                        'email' => $validated['email'],
                        'phone' => $validated['phone'],
                        'phone_normalized' => $phoneNormalized,
                        'message' => $validated['message'] ?? null,
                        'status' => Application::STATUS_PENDING,
                        'rejection_reason' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'locale' => app()->getLocale(),
                    ]);

                    return;
                }

                Application::create($validated + [
                    'phone_normalized' => $phoneNormalized,
                    'contact_key' => $contactKey,
                    'locale' => app()->getLocale(),
                ]);
            });
        } catch (QueryException $e) {
            if (! DatabaseError::isUniqueViolation($e)) {
                throw $e;
            }

            $isDuplicate = true;
        }

        $message = $isDuplicate
            ? __('landing.form.duplicate')
            : __('landing.form.success');

        // AJAX so'rov bo'lsa JSON qaytaramiz
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'duplicate' => $isDuplicate,
                'message' => $message,
            ]);
        }

        return redirect()->route('landing')->with($isDuplicate ? 'duplicate' : 'success', $message);
    }

    private function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['uz', 'ru'], true), 404);

        session(['locale' => $locale]);
        App::setLocale($locale);

        // Faqat oldindan ruxsat berilgan ichki route'larga qaytamiz.
        $destination = match ($request->query('from')) {
            'admin.login' => 'admin.login',
            'admin.dashboard' => 'admin.dashboard',
            default => 'landing',
        };

        // Xato sahifasidan til almashtirilganda foydalanuvchi SHU sahifada qoladi:
        // faqat ichki (nisbiy) manzilga ruxsat — tashqi havolaga chiqish mumkin emas.
        $back = $request->query('back');
        if (is_string($back)
            && $back !== ''
            && str_starts_with($back, '/')
            && ! str_starts_with($back, '//')
            && ! str_contains($back, '\\')) {
            return redirect()->to($back);
        }

        return redirect()->route($destination);
    }

    private function validationMessages(): array
    {
        return [
            'shop_name.required' => __('landing.validation.shop_name_required'),
            'shop_name.max' => __('landing.validation.shop_name_max'),
            'contact_name.required' => __('landing.validation.contact_name_required'),
            'contact_name.max' => __('landing.validation.contact_name_max'),
            'email.required' => __('landing.validation.email_required'),
            'email.email' => __('landing.validation.email_email'),
            'email.max' => __('landing.validation.email_max'),
            'phone.required' => __('landing.validation.phone_required'),
            'phone.regex' => __('landing.validation.phone_regex'),
            'phone.max' => __('landing.validation.phone_max'),
            'message.max' => __('landing.validation.message_max'),
        ];
    }
}
