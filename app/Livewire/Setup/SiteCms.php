<?php

namespace App\Livewire\Setup;

use App\Models\Property;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Hotel-site CMS admin. Edits the JSON `site_content` payload on the
 * current property — that payload backs the public marketing site at
 * /h/{tenant_slug} (Home, About, Contact, Room detail extras, footer).
 *
 * Single page with sections rather than a true page-builder — this is a
 * minimum-viable surface so the demo can show "the hotelier edits this,
 * the public site reflects it instantly". Future work can split into a
 * proper page builder backed by cms_pages / cms_blocks tables.
 */
#[Layout('layouts.app-shell')]
class SiteCms extends Component
{
    use WithFileUploads;

    // Hero
    public string $hero_headline = '';
    public string $hero_subheadline = '';
    public string $hero_cta_label = 'Book your stay';
    public array $heroSlideUploads = [];

    // About
    public string $about_title = '';
    public string $about_body = '';
    public string $about_image = '';
    public ?object $aboutImageUpload = null;

    // Amenities (simple comma-separated for the demo)
    public string $amenities_text = '';

    // Contact
    public string $contact_intro = '';
    public string $contact_address = '';
    public string $contact_phone = '';
    public string $contact_email = '';
    public string $contact_map_embed = '';

    // Footer
    public string $footer_tagline = '';
    public string $footer_copyright = '';

    public function mount(): void
    {
        $property = $this->property();
        if (! $property) return;

        $c = (array) ($property->site_content ?? []);

        $this->hero_headline    = $c['hero']['headline'] ?? ($property->name ?: 'Welcome');
        $this->hero_subheadline = $c['hero']['subheadline'] ?? 'A peaceful stay in the heart of ' . ($property->city ?: 'India');
        $this->hero_cta_label   = $c['hero']['cta_label'] ?? 'Book your stay';

        $this->about_title  = $c['about']['title'] ?? 'About us';
        $this->about_body   = $c['about']['body']  ?? '';
        $this->about_image  = $c['about']['image'] ?? '';

        $this->amenities_text = $c['amenities']['text'] ?? "Free Wi-Fi\nAirport pickup\n24×7 room service\nMulti-cuisine restaurant\nSwimming pool";

        $this->contact_intro    = $c['contact']['intro']    ?? "We'd love to host you. Reach out for reservations or any questions.";
        $this->contact_address  = $c['contact']['address']  ?? ($property->address ?: '');
        $this->contact_phone    = $c['contact']['phone']    ?? ($property->phone ?: '');
        $this->contact_email    = $c['contact']['email']    ?? ($property->email ?: '');
        $this->contact_map_embed= $c['contact']['map_embed']?? '';

        $this->footer_tagline   = $c['footer']['tagline']   ?? '';
        $this->footer_copyright = $c['footer']['copyright'] ?? '© ' . date('Y') . ' ' . ($property->legal_name ?: $property->name);
    }

    public function save(): void
    {
        $this->validate([
            'hero_headline'    => 'required|string|max:200',
            'hero_subheadline' => 'nullable|string|max:300',
            'hero_cta_label'   => 'nullable|string|max:60',
            'heroSlideUploads.*' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,webp',
            'about_title'      => 'nullable|string|max:200',
            'about_body'       => 'nullable|string|max:5000',
            'aboutImageUpload' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,webp',
            'amenities_text'   => 'nullable|string|max:2000',
            'contact_intro'    => 'nullable|string|max:1000',
            'contact_address'  => 'nullable|string|max:500',
            'contact_phone'    => 'nullable|string|max:50',
            'contact_email'    => 'nullable|email|max:200',
            'contact_map_embed'=> 'nullable|string|max:2000',
            'footer_tagline'   => 'nullable|string|max:200',
            'footer_copyright' => 'nullable|string|max:200',
        ]);

        $property = $this->property();
        if (! $property) {
            session()->flash('error', 'No property in context.');
            return;
        }

        $current = (array) ($property->site_content ?? []);

        // Hero — append newly uploaded slides to the existing array
        $heroSlides = (array) ($current['hero']['slides'] ?? []);
        foreach ($this->heroSlideUploads as $upload) {
            if (! $upload) continue;
            $path = $upload->store("public/site/{$property->id}/hero");
            $heroSlides[] = str_replace('public/', '', $path);
        }
        $heroSlides = array_values(array_unique($heroSlides));

        // About — replace image if uploaded
        $aboutImage = $current['about']['image'] ?? '';
        if ($this->aboutImageUpload) {
            $path = $this->aboutImageUpload->store("public/site/{$property->id}/about");
            $aboutImage = str_replace('public/', '', $path);
        }

        $payload = [
            'hero' => [
                'headline'    => $this->hero_headline,
                'subheadline' => $this->hero_subheadline,
                'cta_label'   => $this->hero_cta_label,
                'slides'      => $heroSlides,
            ],
            'about' => [
                'title' => $this->about_title,
                'body'  => $this->about_body,
                'image' => $aboutImage,
            ],
            'amenities' => [
                'text'  => $this->amenities_text,
                'items' => array_values(array_filter(array_map('trim', preg_split("/\r?\n/", $this->amenities_text ?? '')))),
            ],
            'contact' => [
                'intro'     => $this->contact_intro,
                'address'   => $this->contact_address,
                'phone'     => $this->contact_phone,
                'email'     => $this->contact_email,
                'map_embed' => $this->contact_map_embed,
            ],
            'footer' => [
                'tagline'   => $this->footer_tagline,
                'copyright' => $this->footer_copyright,
            ],
        ];

        $property->update(['site_content' => $payload]);
        $this->reset(['heroSlideUploads', 'aboutImageUpload']);
        $this->about_image = $aboutImage;

        session()->flash('success', 'Site content saved. Open the public site to verify.');
    }

    public function removeHeroSlide(string $path): void
    {
        $property = $this->property();
        if (! $property) return;

        $c = (array) ($property->site_content ?? []);
        $slides = array_values(array_filter(
            (array) ($c['hero']['slides'] ?? []),
            fn ($p) => $p !== $path
        ));
        $c['hero']['slides'] = $slides;
        $property->update(['site_content' => $c]);
        try { \Storage::disk('public')->delete($path); } catch (\Throwable $e) { /* ignore */ }
        session()->flash('success', 'Slide removed.');
    }

    public function removeAboutImage(): void
    {
        $property = $this->property();
        if (! $property) return;
        $c = (array) ($property->site_content ?? []);
        $existing = $c['about']['image'] ?? null;
        if ($existing) {
            try { \Storage::disk('public')->delete($existing); } catch (\Throwable $e) { /* ignore */ }
        }
        $c['about']['image'] = '';
        $property->update(['site_content' => $c]);
        $this->about_image = '';
        session()->flash('success', 'About image removed.');
    }

    private function property(): ?Property
    {
        return app(TenantContext::class)->property();
    }

    public function render()
    {
        $property = $this->property();
        $existingSlides = $property ? (array) (($property->site_content['hero']['slides'] ?? [])) : [];

        return view('livewire.setup.site-cms', [
            'property' => $property,
            'existingSlides' => $existingSlides,
            'tenantSlug' => app(TenantContext::class)->tenant()?->slug,
        ]);
    }
}
