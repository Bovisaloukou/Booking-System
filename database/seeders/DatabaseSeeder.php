<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $providerRole = Role::create(['name' => 'provider']);
        $clientRole = Role::create(['name' => 'client']);

        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@booking.test',
        ]);
        $admin->assignRole($adminRole);

        // Create categories
        $categories = [
            ['name' => 'Coiffure', 'slug' => 'coiffure', 'description' => 'Services de coiffure', 'icon' => 'heroicon-o-scissors'],
            ['name' => 'Bien-être', 'slug' => 'bien-etre', 'description' => 'Massages et soins', 'icon' => 'heroicon-o-heart'],
            ['name' => 'Consultation', 'slug' => 'consultation', 'description' => 'Consultations professionnelles', 'icon' => 'heroicon-o-chat-bubble-left-right'],
            ['name' => 'Sport', 'slug' => 'sport', 'description' => 'Coaching et cours de sport', 'icon' => 'heroicon-o-fire'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }

        // Create services
        $services = [
            ['category_id' => 1, 'name' => 'Coupe homme', 'slug' => 'coupe-homme', 'duration' => 30, 'price' => 25.00],
            ['category_id' => 1, 'name' => 'Coupe femme', 'slug' => 'coupe-femme', 'duration' => 45, 'price' => 40.00],
            ['category_id' => 1, 'name' => 'Coloration', 'slug' => 'coloration', 'duration' => 90, 'price' => 65.00],
            ['category_id' => 2, 'name' => 'Massage relaxant', 'slug' => 'massage-relaxant', 'duration' => 60, 'price' => 70.00],
            ['category_id' => 2, 'name' => 'Soin du visage', 'slug' => 'soin-visage', 'duration' => 45, 'price' => 55.00],
            ['category_id' => 3, 'name' => 'Consultation juridique', 'slug' => 'consultation-juridique', 'duration' => 60, 'price' => 100.00],
            ['category_id' => 3, 'name' => 'Consultation nutrition', 'slug' => 'consultation-nutrition', 'duration' => 45, 'price' => 60.00],
            ['category_id' => 4, 'name' => 'Coaching personnel', 'slug' => 'coaching-personnel', 'duration' => 60, 'price' => 50.00],
            ['category_id' => 4, 'name' => 'Cours de yoga', 'slug' => 'cours-yoga', 'duration' => 75, 'price' => 35.00],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }

        // Create providers
        $providerNames = ['Marie Dupont', 'Jean Martin', 'Sophie Bernard'];
        foreach ($providerNames as $i => $name) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => 'provider'.($i + 1).'@booking.test',
            ]);
            $user->assignRole($providerRole);

            $provider = Provider::create([
                'user_id' => $user->id,
                'bio' => 'Prestataire expérimenté avec plus de '.($i + 1) * 5 ." ans d'expérience.",
                'speciality' => $categories[$i]['name'],
                'hourly_rate' => 50 + ($i * 10),
                'is_active' => true,
            ]);

            // Attach services to provider
            $serviceIds = Service::where('category_id', $i + 1)->pluck('id');
            $provider->services()->attach($serviceIds);

            // Create time slots for next 14 days
            for ($day = 0; $day < 14; $day++) {
                $date = Carbon::today()->addDays($day);
                if ($date->isWeekend()) {
                    continue;
                }

                for ($hour = 9; $hour < 18; $hour++) {
                    TimeSlot::create([
                        'provider_id' => $provider->id,
                        'date' => $date->toDateString(),
                        'start_time' => sprintf('%02d:00', $hour),
                        'end_time' => sprintf('%02d:00', $hour + 1),
                        'is_available' => true,
                    ]);
                }
            }
        }

        // Create client users
        $clients = [];
        for ($i = 1; $i <= 5; $i++) {
            $client = User::factory()->create([
                'email' => "client{$i}@booking.test",
            ]);
            $client->assignRole($clientRole);
            $clients[] = $client;
        }

        // Create some bookings
        $statuses = ['pending', 'confirmed', 'completed'];
        foreach ($clients as $i => $client) {
            $provider = Provider::find(($i % 3) + 1);
            $service = $provider->services->first();
            $slot = $provider->timeSlots()->where('is_available', true)->first();

            if ($slot && $service) {
                $booking = Booking::create([
                    'client_id' => $client->id,
                    'provider_id' => $provider->id,
                    'service_id' => $service->id,
                    'time_slot_id' => $slot->id,
                    'date' => $slot->date,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'total_price' => $service->price,
                    'status' => $statuses[$i % 3],
                    'confirmed_at' => $statuses[$i % 3] !== 'pending' ? now() : null,
                    'completed_at' => $statuses[$i % 3] === 'completed' ? now() : null,
                ]);

                $slot->markAsBooked();

                // Add reviews for completed bookings
                if ($booking->status->value === 'completed') {
                    Review::create([
                        'booking_id' => $booking->id,
                        'client_id' => $client->id,
                        'provider_id' => $provider->id,
                        'rating' => rand(4, 5),
                        'comment' => 'Excellent service, je recommande !',
                    ]);
                }
            }
        }
    }
}
