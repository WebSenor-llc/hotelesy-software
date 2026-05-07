<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ============ REVIEWS ============ */
        Schema::create('review_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->enum('source', ['google', 'tripadvisor', 'booking_com', 'mmt', 'goibibo', 'agoda', 'expedia', 'in_house', 'other']);
            $table->string('external_property_id')->nullable()->comment('e.g. TripAdvisor location_id, Google place_id');
            $table->string('listing_url')->nullable();
            $table->json('credentials')->nullable()->comment('OAuth tokens, encrypted');
            $table->boolean('auto_fetch')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'source']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('review_sources')->nullOnDelete();
            $table->string('source')->comment('Denormalised from review_sources.source for fast filter');
            $table->string('external_review_id')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_country', 50)->nullable();
            $table->date('stay_date')->nullable();
            $table->date('review_date');
            $table->decimal('rating', 3, 1)->comment('Normalised to 5-point scale');
            $table->decimal('original_rating', 4, 1)->nullable()->comment('Source rating before normalisation');
            $table->unsignedTinyInteger('original_rating_max')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->json('aspect_ratings')->nullable()->comment('cleanliness, service, location, etc.');
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->string('language', 10)->default('en');
            $table->text('response_text')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'review_date']);
            $table->index(['property_id', 'rating']);
            $table->unique(['source', 'external_review_id']);
        });

        Schema::create('review_response_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('trigger', ['high_rating', 'low_rating', 'specific_keyword', 'manual']);
            $table->json('trigger_config')->nullable()->comment('keywords, rating threshold');
            $table->text('template_body');
            $table->boolean('auto_send')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ============ REVENUE MANAGEMENT ============ */
        Schema::create('revenue_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('booking_com_url')->nullable();
            $table->string('mmt_url')->nullable();
            $table->string('tripadvisor_url')->nullable();
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->boolean('is_primary_compset')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Rate shopper — scraped competitor rates per stay date
        Schema::create('revenue_rate_shop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained('revenue_competitors')->cascadeOnDelete();
            $table->date('shop_date')->comment('When scraped');
            $table->date('stay_date');
            $table->string('source', 30)->default('booking.com');
            $table->string('room_type_label')->nullable();
            $table->decimal('rate', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->boolean('available')->default(true);
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'stay_date']);
            $table->index(['competitor_id', 'shop_date']);
        });

        // Pricing rules — dynamic pricing logic
        Schema::create('revenue_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('rule_type', [
                'occupancy_based', 'days_to_arrival', 'day_of_week',
                'season', 'event', 'compset_position', 'min_max_floor',
            ]);
            $table->json('conditions');
            $table->enum('action', ['increase_percent', 'decrease_percent', 'set_to', 'increase_fixed', 'decrease_fixed']);
            $table->decimal('value', 10, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedSmallInteger('priority')->default(100)->comment('Lower number = applied first');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['property_id', 'is_active']);
        });

        // Forecast — model output: predicted occupancy & ARR
        Schema::create('revenue_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->date('stay_date');
            $table->decimal('forecast_occupancy_pct', 5, 2);
            $table->decimal('forecast_arr', 12, 2);
            $table->decimal('forecast_revpar', 12, 2);
            $table->json('booking_pace')->nullable()->comment('Pickup curve: rooms booked vs days-to-arrival');
            $table->string('model_version', 30)->nullable();
            $table->timestamps();
            $table->index(['property_id', 'stay_date']);
        });

        /* ============ AMENITIES & ADD-ONS ============ */
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['transport', 'meal', 'spa', 'tour', 'experience', 'merchandise', 'utility', 'other'])->default('other');
            $table->enum('pricing_type', ['per_stay', 'per_night', 'per_person', 'per_person_per_night', 'flat']);
            $table->decimal('price', 10, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->json('image_urls')->nullable();
            $table->json('metadata')->nullable()->comment('e.g. spa duration_minutes, transport vehicle_type');
            $table->boolean('available_at_booking')->default(true)->comment('Show on booking engine');
            $table->boolean('available_at_checkin')->default(true);
            $table->boolean('available_in_stay')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('amenity_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 50)->unique();
            $table->date('service_date')->nullable();
            $table->time('service_time')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', ['pending', 'confirmed', 'fulfilled', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'service_date']);
        });

        /* ============ CMS / HOTEL WEBSITE ============ */
        Schema::create('cms_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('subdomain', 100)->unique()->comment('e.g. mirajlakepalace.miraj-hotels.com');
            $table->string('custom_domain')->nullable()->unique();
            $table->string('domain_verification_token')->nullable();
            $table->boolean('domain_verified')->default(false);
            $table->string('site_title');
            $table->text('site_description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('theme', 50)->default('elegant');
            $table->json('theme_config')->nullable()->comment('Color palette, fonts, custom CSS');
            $table->json('seo_meta')->nullable();
            $table->string('analytics_ga_id')->nullable();
            $table->string('analytics_meta_pixel')->nullable();
            $table->string('analytics_gtm_id')->nullable();
            $table->json('contact')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('booking_engine_enabled')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('cms_sites')->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('title');
            $table->enum('page_type', ['home', 'rooms', 'dining', 'amenities', 'gallery', 'contact', 'about', 'offers', 'custom'])->default('custom');
            $table->json('blocks')->comment('Page builder blocks: hero, gallery, text, room_grid, amenity_list, testimonial, cta, map, contact_form');
            $table->json('seo_meta')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
            $table->unique(['site_id', 'slug']);
        });

        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('cms_sites')->cascadeOnDelete();
            $table->string('filename');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size_bytes');
            $table->string('storage_path');
            $table->string('public_url');
            $table->string('alt_text')->nullable();
            $table->json('focal_point')->nullable()->comment('{x, y} for crop centring');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('cms_sites');
        Schema::dropIfExists('amenity_orders');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('revenue_forecasts');
        Schema::dropIfExists('revenue_pricing_rules');
        Schema::dropIfExists('revenue_rate_shop');
        Schema::dropIfExists('revenue_competitors');
        Schema::dropIfExists('review_response_templates');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('review_sources');
    }
};
