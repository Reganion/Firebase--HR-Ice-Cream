<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerPageController extends Controller
{
    public function __construct(private FirestoreService $firestore)
    {
    }

    public function home()
    {
        $flavors = $this->flavorsForHomeView()->take(4)->values();
        $feedbacks = $this->feedbacksForHomeView();
        return view('customer.home', compact('flavors', 'feedbacks'));
    }

    /**
     * @return Collection<int, object>
     */
    private function flavorsForHomeView(): Collection
    {
        return collect($this->firestore->rememberAll('flavors', 120))
            ->sortByDesc(fn (array $f) => (string) ($f['created_at'] ?? ''))
            ->values()
            ->map(function (array $f) {
                $image = trim((string) ($f['image'] ?? ''));
                if ($image === '') {
                    $image = 'img/default-product.png';
                }
                $mobileImage = trim((string) ($f['mobile_image'] ?? ''));
                if ($mobileImage === '') {
                    $mobileImage = $image;
                }

                return (object) [
                    'id' => $f['id'] ?? null,
                    'name' => (string) ($f['name'] ?? ''),
                    'image' => $image,
                    'mobile_image' => $mobileImage,
                    'price' => (float) ($f['price'] ?? 0),
                    'rating' => $f['rating'] ?? '5.0',
                    'reviews' => $f['reviews'] ?? '0',
                    'description' => (string) ($f['description'] ?? ''),
                ];
            });
    }

    /**
     * @return Collection<int, object>
     */
    private function feedbacksForHomeView(): Collection
    {
        return collect($this->firestore->rememberAll('feedback', 120))
            ->sortByDesc(fn (array $fb) => (string) ($fb['feedback_date'] ?? ''))
            ->values()
            ->map(function (array $fb) {
                $photo = trim((string) ($fb['photo'] ?? ''));
                if ($photo === '') {
                    $photo = 'img/logo.png';
                }
                $date = $fb['feedback_date'] ?? null;
                try {
                    $parsed = $date ? Carbon::parse((string) $date) : Carbon::now();
                } catch (\Throwable) {
                    $parsed = Carbon::now();
                }

                return (object) [
                    'photo' => $photo,
                    'customer_name' => (string) ($fb['customer_name'] ?? 'Customer'),
                    'rating' => max(0, min(5, (int) ($fb['rating'] ?? 0))),
                    'testimonial' => (string) ($fb['testimonial'] ?? ''),
                    'feedback_date' => $parsed,
                ];
            });
    }

    public function about()
    {
        return view('customer.aboutus');
    }

    public function Customerlogin()
    {
        return view('customer.login');
    }

    public function register()
    {
        return view('customer.register');
    }
}
