@extends('layouts.app')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('grounds.show', $ground) }}" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-700 dark:text-slate-200 transition" title="Back to Ground">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <span class="text-[11px] font-black uppercase text-amber-500 tracking-wider">EDIT VENUE • OWNER CONTROLS</span>
            <h2 class="font-black text-xl sm:text-2xl text-slate-900 dark:text-white leading-tight">Edit Ground: {{ $ground->name }}</h2>
        </div>
    </div>
@endsection

@section('content')
<div class="py-8 bg-slate-900 min-h-screen text-slate-100 font-sans">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        @if($errors->any())
            <div class="p-4 mb-6 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs font-bold space-y-1">
                @foreach($errors->all() as $err)
                    <div>• {{ $err }}</div>
                @endforeach
            </div>
        @endif

        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">

            <div class="pb-4 border-b border-white/10 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-black text-white flex items-center gap-2">
                        <span>🏟️ Update Ground Details & Photos</span>
                    </h3>
                    <p class="text-slate-400 text-xs mt-1">Make changes to pitch type, pricing, slot timings, amenities or photos of your cricket arena.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-black bg-blue-500/20 text-blue-300 border border-blue-500/30">
                    👑 Owner Mode
                </span>
            </div>

            @php
                $actualImages = is_array($ground->images) && count($ground->images) > 0
                    ? array_values(array_filter($ground->images))
                    : ($ground->image_url ? [$ground->image_url] : []);
            @endphp

            <form method="POST" action="{{ route('grounds.update', $ground) }}" enctype="multipart/form-data" class="space-y-5 text-xs"
                  x-data="{
                      existingPhotos: {{ json_encode($actualImages) }},
                      newPhotos: [],
                      urlMode: {{ !empty($ground->image_url) ? 'true' : 'false' }},
                      errorMessage: '',
                      get totalCount() {
                          return this.existingPhotos.length + this.newPhotos.length;
                      },
                      removeExisting(index) {
                          this.existingPhotos.splice(index, 1);
                      },
                      removeNew(index) {
                          this.newPhotos.splice(index, 1);
                      },
                      handleFiles(e) {
                          const files = Array.from(e.target.files || []);
                          if (this.totalCount + files.length > 5) {
                              this.errorMessage = 'You can have a maximum of 5 photos in total.';
                              setTimeout(() => this.errorMessage = '', 4000);
                          }
                          const allowed = files.slice(0, Math.max(0, 5 - this.totalCount));
                          allowed.forEach(file => {
                              if (!file.type.startsWith('image/')) return;
                              const reader = new FileReader();
                              reader.onload = (event) => {
                                  this.newPhotos.push({
                                      name: file.name,
                                      size: (file.size / 1024).toFixed(0) + ' KB',
                                      base64: event.target.result
                                  });
                              };
                              reader.readAsDataURL(file);
                          });
                          e.target.value = '';
                      }
                  }">
                @csrf
                @method('PUT')

                <!-- Retained Existing Photos -->
                <template x-for="(photoUrl, idx) in existingPhotos" :key="'existing-' + idx">
                    <input type="hidden" name="existing_photos[]" :value="photoUrl">
                </template>

                <!-- New Base64 Photos -->
                <template x-for="(photo, idx) in newPhotos" :key="'new-' + idx">
                    <input type="hidden" name="photos_base64[]" :value="photo.base64">
                </template>

                <!-- Ground Name -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Ground / Venue Name *</label>
                    <input type="text" name="name" value="{{ old('name', $ground->name) }}" required placeholder="e.g. Shivaji Park Cricket Ground, Lords Arena Turf"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <!-- City & Pitch Type -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">City *</label>
                        <input type="text" name="city" value="{{ old('city', $ground->city) }}" required placeholder="e.g. Shimla, Mumbai, Delhi, Pune"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Pitch Type *</label>
                        <select name="pitch_type" required class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="Turf Pitch" {{ old('pitch_type', $ground->pitch_type) === 'Turf Pitch' ? 'selected' : '' }}>🌱 Turf Pitch</option>
                            <option value="Natural Grass" {{ old('pitch_type', $ground->pitch_type) === 'Natural Grass' ? 'selected' : '' }}>🌿 Natural Grass</option>
                            <option value="Cement / Matting" {{ old('pitch_type', $ground->pitch_type) === 'Cement / Matting' ? 'selected' : '' }}>🏏 Cement / Matting</option>
                            <option value="Box Cricket / Astro Turf" {{ old('pitch_type', $ground->pitch_type) === 'Box Cricket / Astro Turf' ? 'selected' : '' }}>⚡ Box Cricket / Astro Turf</option>
                        </select>
                    </div>
                </div>

                <!-- Full Address -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Full Address / Landmark *</label>
                    <input type="text" name="address" value="{{ old('address', $ground->address) }}" required placeholder="e.g. Verma Niwas, Opposite New Apple Mandi Near Canara Bank, Sanjauli, Shimla"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <!-- Pricing & Slot Duration -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Price Per Slot (₹) *</label>
                        <input type="number" step="0.01" name="price_per_slot" value="{{ old('price_per_slot', $ground->price_per_slot) }}" required placeholder="1500"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Slot Duration *</label>
                        <select name="slot_duration" required class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="2 Hours" {{ old('slot_duration', $ground->slot_duration) === '2 Hours' ? 'selected' : '' }}>2 Hours</option>
                            <option value="3 Hours" {{ old('slot_duration', $ground->slot_duration) === '3 Hours' ? 'selected' : '' }}>3 Hours (Standard T20 Match)</option>
                            <option value="4 Hours" {{ old('slot_duration', $ground->slot_duration) === '4 Hours' ? 'selected' : '' }}>4 Hours</option>
                            <option value="Full Match" {{ old('slot_duration', $ground->slot_duration) === 'Full Match' ? 'selected' : '' }}>Per Match</option>
                            <option value="Full Day" {{ old('slot_duration', $ground->slot_duration) === 'Full Day' ? 'selected' : '' }}>Full Day Tournament</option>
                        </select>
                    </div>
                </div>

                <!-- Contact & WhatsApp -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Contact Phone Number *</label>
                        <input type="text" name="phone" value="{{ old('phone', $ground->phone) }}" required placeholder="e.g. 9876543210"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">WhatsApp Number (Optional)</label>
                        <input type="text" name="whatsapp" value="{{ old('whatsapp', $ground->whatsapp) }}" placeholder="WhatsApp number for team bookings"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <!-- Listing Status -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Venue Availability Status *</label>
                    <select name="status" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="available" {{ old('status', $ground->status) === 'available' ? 'selected' : '' }}>🟢 Available & Accepting Bookings</option>
                        <option value="maintenance" {{ old('status', $ground->status) === 'maintenance' ? 'selected' : '' }}>🟡 Under Maintenance / Pitch Repair</option>
                        <option value="booked" {{ old('status', $ground->status) === 'booked' ? 'selected' : '' }}>🔴 Fully Booked</option>
                        <option value="inactive" {{ old('status', $ground->status) === 'inactive' ? 'selected' : '' }}>⚪ Inactive / Hidden</option>
                    </select>
                </div>

                <!-- Ground Amenities Checkboxes -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-2">Ground Facilities & Amenities</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 p-4 rounded-2xl bg-black/30 border border-white/5">
                        @php
                            $amenityList = [
                                'Floodlights',
                                'Pavilion',
                                'Drinking Water',
                                'Parking',
                                'Net Practice',
                                'Boundary Rope',
                                'Changing Room',
                                'Washroom',
                                'Live Streaming Stand',
                            ];
                            $currentAmenities = old('amenities', $ground->amenities ?? []);
                            if (!is_array($currentAmenities)) {
                                $currentAmenities = [];
                            }
                        @endphp
                        @foreach($amenityList as $am)
                            <label class="flex items-center gap-2 cursor-pointer text-slate-300 hover:text-white">
                                <input type="checkbox" name="amenities[]" value="{{ $am }}"
                                       {{ in_array($am, $currentAmenities) ? 'checked' : '' }}
                                       class="rounded bg-slate-950 border-white/20 text-emerald-600 focus:ring-emerald-500">
                                <span>{{ $am }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Ground Photos Upload & Manager (Up to 5 Photos) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block font-bold text-slate-300 uppercase tracking-wider">
                                Ground Photos <span class="text-emerald-400 font-normal">(Up to 5 Photos)</span>
                            </label>
                            <p class="text-[11px] text-slate-400 mt-0.5">Manage existing photos or add new ones. Click ✕ to remove any photo.</p>
                        </div>
                        <span class="text-xs font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 rounded-lg"
                              x-text="totalCount + ' / 5 Total Photos'"></span>
                    </div>

                    <!-- Error notification -->
                    <div x-show="errorMessage" x-cloak class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-bold flex items-center gap-2">
                        <span>⚠️</span>
                        <span x-text="errorMessage"></span>
                    </div>

                    <!-- Existing Saved Photos Section -->
                    <div x-show="existingPhotos.length > 0" class="space-y-2">
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Current Photos</span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                            <template x-for="(pUrl, index) in existingPhotos" :key="'ex-grid-' + index">
                                <div class="relative rounded-xl overflow-hidden bg-slate-950 border border-white/10 group shadow-md">
                                    <img :src="pUrl" class="w-full h-24 sm:h-28 object-cover">

                                    <div x-show="index === 0" class="absolute top-1.5 left-1.5 bg-emerald-600 text-white font-extrabold text-[9px] px-1.5 py-0.5 rounded shadow">
                                        ⭐ Cover Photo
                                    </div>
                                    <div x-show="index > 0" class="absolute top-1.5 left-1.5 bg-black/70 backdrop-blur text-slate-300 font-bold text-[9px] px-1.5 py-0.5 rounded shadow"
                                         x-text="'Photo #' + (index + 1)">
                                    </div>

                                    <button type="button"
                                            @click.stop="removeExisting(index)"
                                            class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-red-600 hover:bg-red-500 text-white font-black text-xs flex items-center justify-center shadow-lg transition"
                                            title="Delete photo">
                                        ✕
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Upload Picker for New Photos (Visible if total < 5) -->
                    <div x-show="totalCount < 5"
                         class="border-2 border-dashed border-emerald-500/30 hover:border-emerald-500/60 bg-emerald-500/[0.03] hover:bg-emerald-500/[0.07] rounded-2xl p-5 text-center transition cursor-pointer group"
                         @click="$refs.newGroundPhotosInput.click()">
                        <input type="file"
                               x-ref="newGroundPhotosInput"
                               name="photos[]"
                               multiple
                               accept="image/jpeg,image/png,image/jpg,image/webp"
                               class="hidden"
                               @change="handleFiles($event)">

                        <div class="flex flex-col items-center justify-center pointer-events-none">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-xl group-hover:scale-110 transition">
                                📸
                            </div>
                            <span class="font-extrabold text-white text-xs sm:text-sm mt-2">Add More Photos</span>
                            <span class="text-slate-400 text-[11px] mt-0.5">Select up to <span x-text="5 - totalCount"></span> more photos</span>
                            <span class="inline-block mt-2.5 px-3 py-1 rounded-lg bg-emerald-600/30 text-emerald-300 border border-emerald-500/40 text-[11px] font-bold">
                                + Choose Photos
                            </span>
                        </div>
                    </div>

                    <!-- Newly Selected Photos Preview -->
                    <div x-show="newPhotos.length > 0" x-cloak class="space-y-2 pt-2">
                        <span class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider block">Newly Added Photos (Pending Save)</span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                            <template x-for="(photo, index) in newPhotos" :key="'new-grid-' + index">
                                <div class="relative rounded-xl overflow-hidden bg-slate-950 border border-emerald-500/30 group shadow-md ring-1 ring-emerald-500/40">
                                    <img :src="photo.base64" class="w-full h-24 sm:h-28 object-cover">

                                    <div class="absolute top-1.5 left-1.5 bg-emerald-500 text-slate-950 font-extrabold text-[9px] px-1.5 py-0.5 rounded shadow">
                                        ✨ New
                                    </div>

                                    <button type="button"
                                            @click.stop="removeNew(index)"
                                            class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-red-600 hover:bg-red-500 text-white font-black text-xs flex items-center justify-center shadow-lg transition"
                                            title="Remove new photo">
                                        ✕
                                    </button>

                                    <div class="p-1.5 bg-slate-900/90 text-[10px] text-slate-400 truncate text-center" x-text="photo.size"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Optional URL fallback toggle -->
                    <div class="pt-1">
                        <button type="button" @click="urlMode = !urlMode" class="text-[11px] font-bold text-emerald-400 hover:text-emerald-300 flex items-center gap-1">
                            <span x-text="urlMode ? '▾ Hide Photo URL field' : '▸ Or add ground photo by URL'"></span>
                        </button>
                        <div x-show="urlMode" x-cloak class="mt-2">
                            <input type="url" name="image_url" value="{{ old('image_url', $ground->image_url) }}" placeholder="https://images.unsplash.com/photo-..."
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Ground Description & Match Guidelines</label>
                    <textarea name="description" rows="3" placeholder="Tell captains about ground dimensions, boundary size, spikes allowed/not allowed, ball type recommended..."
                              class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description', $ground->description) }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                    <a href="{{ route('grounds.show', $ground) }}" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 font-bold text-xs transition">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-emerald-600 hover:from-amber-400 hover:to-emerald-500 text-slate-950 font-black text-sm shadow-xl shadow-amber-500/20 transition transform active:scale-95">
                        Save Changes 🏟️✨
                    </button>
                </div>

            </form>

        </div>

    </div>
</div>
@endsection
