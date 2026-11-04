@extends('layouts.app')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('grounds.index') }}" class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 transition" title="Back to Grounds">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <span class="text-[11px] font-black uppercase text-emerald-600 tracking-wider">GROUND HOST PARTNER</span>
            <h2 class="font-black text-xl text-gray-900 leading-tight">Register Cricket Ground / Venue</h2>
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

            <div class="pb-4 border-b border-white/10">
                <h3 class="text-lg font-black text-white flex items-center gap-2">
                    <span>🏟️ Ground Details & Amenities</span>
                </h3>
                <p class="text-slate-400 text-xs mt-1">List your cricket ground, turf pitch, or box arena so local cricket teams can book match slots directly.</p>
            </div>

            <form method="POST" action="{{ route('grounds.store') }}" enctype="multipart/form-data" class="space-y-5 text-xs"
                  x-data="{
                      photos: [],
                      urlMode: false,
                      errorMessage: '',
                      handleFiles(e) {
                          const files = Array.from(e.target.files || []);
                          if (this.photos.length + files.length > 5) {
                              this.errorMessage = 'You can select a maximum of 5 photos.';
                              setTimeout(() => this.errorMessage = '', 4000);
                          }
                          const allowed = files.slice(0, 5 - this.photos.length);
                          allowed.forEach(file => {
                              if (!file.type.startsWith('image/')) return;
                              const reader = new FileReader();
                              reader.onload = (event) => {
                                  this.photos.push({
                                      name: file.name,
                                      size: (file.size / 1024).toFixed(0) + ' KB',
                                      base64: event.target.result
                                  });
                              };
                              reader.readAsDataURL(file);
                          });
                          e.target.value = '';
                      },
                      removePhoto(index) {
                          this.photos.splice(index, 1);
                      }
                  }">
                @csrf

                <!-- Ground Name -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Ground / Venue Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Shivaji Park Cricket Ground, Lords Arena Turf"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <!-- City & Address -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">City *</label>
                        <input type="text" name="city" value="{{ old('city') }}" required placeholder="e.g. Mumbai, Delhi, Bengaluru, Pune"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Pitch Type *</label>
                        <select name="pitch_type" required class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="Turf Pitch">🌱 Turf Pitch</option>
                            <option value="Natural Grass">🌿 Natural Grass</option>
                            <option value="Cement / Matting">🏏 Cement / Matting</option>
                            <option value="Box Cricket / Astro Turf">⚡ Box Cricket / Astro Turf</option>
                        </select>
                    </div>
                </div>

                <!-- Full Address -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Full Address / Landmark *</label>
                    <input type="text" name="address" value="{{ old('address') }}" required placeholder="e.g. Plot No 12, Near Metro Station, Sector 62"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <!-- Pricing & Slot Duration -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Price Per Slot (₹) *</label>
                        <input type="number" step="0.01" name="price_per_slot" value="{{ old('price_per_slot', '1500') }}" required placeholder="1500"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Slot Duration *</label>
                        <select name="slot_duration" required class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="2 Hours">2 Hours</option>
                            <option value="3 Hours" selected>3 Hours (Standard T20 Match)</option>
                            <option value="4 Hours">4 Hours</option>
                            <option value="Full Match">Per Match</option>
                            <option value="Full Day">Full Day Tournament</option>
                        </select>
                    </div>
                </div>

                <!-- Contact & WhatsApp -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Contact Phone Number *</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="e.g. 9876543210"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">WhatsApp Number (Optional)</label>
                        <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="WhatsApp number for direct team chats"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
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
                        @endphp
                        @foreach($amenityList as $am)
                            <label class="flex items-center gap-2 cursor-pointer text-slate-300 hover:text-white">
                                <input type="checkbox" name="amenities[]" value="{{ $am }}" class="rounded bg-slate-950 border-white/20 text-emerald-600 focus:ring-emerald-500">
                                <span>{{ $am }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Ground Photos Upload (Up to 5 Photos) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block font-bold text-slate-300 uppercase tracking-wider">
                                Ground Photos <span class="text-emerald-400 font-normal">(Select 4 to 5 Photos)</span>
                            </label>
                            <p class="text-[11px] text-slate-400 mt-0.5">Upload pitch, pavilion, boundary, lights & turf photos for teams to inspect.</p>
                        </div>
                        <span class="text-xs font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 rounded-lg"
                              x-text="photos.length + ' / 5 Selected'">0 / 5 Selected</span>
                    </div>

                    <!-- Hidden inputs to submit base64 data to backend -->
                    <template x-for="(photo, idx) in photos" :key="idx">
                        <input type="hidden" name="photos_base64[]" :value="photo.base64">
                    </template>

                    <!-- Error notification -->
                    <div x-show="errorMessage" x-cloak class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-bold flex items-center gap-2">
                        <span>⚠️</span>
                        <span x-text="errorMessage"></span>
                    </div>

                    <!-- Upload Drop Zone / Picker -->
                    <div x-show="photos.length < 5"
                         class="border-2 border-dashed border-emerald-500/30 hover:border-emerald-500/60 bg-emerald-500/[0.03] hover:bg-emerald-500/[0.07] rounded-2xl p-6 text-center transition cursor-pointer group"
                         @click="$refs.groundPhotosInput.click()">
                        <input type="file"
                               x-ref="groundPhotosInput"
                               name="photos[]"
                               multiple
                               accept="image/jpeg,image/png,image/jpg,image/webp"
                               class="hidden"
                               @change="handleFiles($event)">

                        <div class="flex flex-col items-center justify-center pointer-events-none">
                            <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-2xl group-hover:scale-110 transition">
                                📸
                            </div>
                            <span class="font-extrabold text-white text-sm mt-3">Click to Select Photos from Device</span>
                            <span class="text-slate-400 text-xs mt-1">Select up to 5 photos (JPG, PNG, WebP)</span>
                            <span class="inline-block mt-3 px-3 py-1 rounded-lg bg-emerald-600/30 text-emerald-300 border border-emerald-500/40 text-[11px] font-bold">
                                + Choose Ground Photos
                            </span>
                        </div>
                    </div>

                    <!-- Thumbnail Preview Grid -->
                    <div x-show="photos.length > 0" x-cloak class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 pt-2">
                        <template x-for="(photo, index) in photos" :key="index">
                            <div class="relative rounded-xl overflow-hidden bg-slate-950 border border-white/10 group shadow-md">
                                <img :src="photo.base64" class="w-full h-24 sm:h-28 object-cover">
                                
                                <!-- Cover / Primary Badge -->
                                <div x-show="index === 0" class="absolute top-1.5 left-1.5 bg-emerald-600 text-white font-extrabold text-[9px] px-1.5 py-0.5 rounded shadow">
                                    ⭐ Cover Photo
                                </div>
                                <div x-show="index > 0" class="absolute top-1.5 left-1.5 bg-black/70 backdrop-blur text-slate-300 font-bold text-[9px] px-1.5 py-0.5 rounded shadow"
                                     x-text="'Photo #' + (index + 1)">
                                </div>

                                <!-- Remove Button -->
                                <button type="button"
                                        @click.stop="removePhoto(index)"
                                        class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-red-600 hover:bg-red-500 text-white font-black text-xs flex items-center justify-center shadow-lg transition"
                                        title="Remove photo">
                                    ✕
                                </button>

                                <div class="p-1.5 bg-slate-900/90 text-[10px] text-slate-400 truncate text-center" x-text="photo.size"></div>
                            </div>
                        </template>
                    </div>

                    <!-- Optional URL fallback toggle -->
                    <div class="pt-1">
                        <button type="button" @click="urlMode = !urlMode" class="text-[11px] font-bold text-emerald-400 hover:text-emerald-300 flex items-center gap-1">
                            <span x-text="urlMode ? '▾ Hide Photo URL field' : '▸ Or add ground photo by URL'"></span>
                        </button>
                        <div x-show="urlMode" x-cloak class="mt-2">
                            <input type="url" name="image_url" value="{{ old('image_url') }}" placeholder="https://images.unsplash.com/photo-..."
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Ground Description & Match Guidelines</label>
                    <textarea name="description" rows="3" placeholder="Tell captains about ground dimensions, boundary size, spikes allowed/not allowed, ball type recommended..."
                              class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description') }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                    <a href="{{ route('grounds.index') }}" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 font-bold text-xs transition">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-sm shadow-xl shadow-emerald-600/30 transition transform active:scale-95">
                        Register & Publish Ground 🏟️
                    </button>
                </div>

            </form>

        </div>

    </div>
</div>
@endsection
