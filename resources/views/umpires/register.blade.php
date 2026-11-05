@extends('layouts.app')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('umpires.index') }}" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-700 dark:text-slate-200 transition" title="Back to Umpires">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <span class="text-[11px] font-black uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">OFFICIAL REGISTRATION</span>
            <h2 class="font-black text-xl sm:text-2xl text-slate-900 dark:text-white leading-tight">
                {{ $profile ? 'Update Umpire Official Profile' : 'Register as Cricket Umpire' }}
            </h2>
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
                    <span>⚖️ Cricket Official Qualifications & Fee</span>
                </h3>
                <p class="text-slate-400 text-xs mt-1">Get booked by tournament organizers and local captains to officiate matches in your city.</p>
            </div>

            <form method="POST" action="{{ route('umpires.profile.store') }}" enctype="multipart/form-data" class="space-y-5 text-xs" x-data="{ photoPreview: '{{ old('photo_url', $profile?->photo_url) }}' }">
                @csrf

                <!-- Name & City -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Official Full Name *</label>
                        <input type="text" name="name" value="{{ old('name', $profile?->name ?? Auth::user()->name) }}" required placeholder="e.g. Anil Kumar Sharma"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Operating City *</label>
                        <input type="text" name="city" value="{{ old('city', $profile?->city ?? 'Mumbai') }}" required placeholder="e.g. Mumbai, Delhi, Jaipur"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <!-- Experience & Matches Umpired -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Years of Umpiring Experience *</label>
                        <input type="number" name="experience_years" value="{{ old('experience_years', $profile?->experience_years ?? 3) }}" min="0" required placeholder="3"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Approx. Matches Officiated *</label>
                        <input type="number" name="matches_umpired" value="{{ old('matches_umpired', $profile?->matches_umpired ?? 45) }}" min="0" required placeholder="45"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <!-- Certification Level & Match Fee -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Certification Level *</label>
                        <select name="certification_level" required class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="Club / Local League Umpire" {{ old('certification_level', $profile?->certification_level) === 'Club / Local League Umpire' ? 'selected' : '' }}>🏏 Club / Local League Umpire</option>
                            <option value="BCCI Certified Level 1" {{ old('certification_level', $profile?->certification_level) === 'BCCI Certified Level 1' ? 'selected' : '' }}>🎖️ BCCI Certified Level 1</option>
                            <option value="State Association Certified" {{ old('certification_level', $profile?->certification_level) === 'State Association Certified' ? 'selected' : '' }}>🏛️ State Association Certified</option>
                            <option value="Veteran Match Official" {{ old('certification_level', $profile?->certification_level) === 'Veteran Match Official' ? 'selected' : '' }}>⭐ Veteran Match Official (10+ Yrs)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Fee Per Match (₹) *</label>
                        <input type="number" step="0.01" name="fee_per_match" value="{{ old('fee_per_match', $profile?->fee_per_match ?? '800') }}" required placeholder="800"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <!-- Match Types Checkboxes -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-2">Match Types You Officiate</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-4 rounded-2xl bg-black/30 border border-white/5">
                        @php
                            $typesList = ['Leather Ball', 'Tennis Ball', 'T20 Matches', 'Day/Night Tournaments', 'Box Cricket', 'Corporate League'];
                            $currentTypes = old('match_types', $profile?->match_types ?? ['Leather Ball', 'Tennis Ball', 'T20 Matches']);
                        @endphp
                        @foreach($typesList as $t)
                            <label class="flex items-center gap-2 cursor-pointer text-slate-300 hover:text-white">
                                <input type="checkbox" name="match_types[]" value="{{ $t }}" {{ in_array($t, $currentTypes) ? 'checked' : '' }} class="rounded bg-slate-950 border-white/20 text-indigo-600 focus:ring-indigo-500">
                                <span>{{ $t }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Contact & WhatsApp -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Contact Phone Number *</label>
                        <input type="text" name="phone" value="{{ old('phone', $profile?->phone ?? Auth::user()->phone) }}" required placeholder="e.g. 9876543210"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">WhatsApp Number (Optional)</label>
                        <input type="text" name="whatsapp" value="{{ old('whatsapp', $profile?->whatsapp ?? Auth::user()->phone) }}" placeholder="WhatsApp number"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <!-- Profile Photo Upload & URL -->
                <div class="p-4 rounded-2xl bg-black/30 border border-white/5 space-y-3">
                    <label class="block font-bold text-slate-300 uppercase tracking-wider">Umpire Profile Photo (Upload or URL)</label>
                    
                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <!-- Photo Preview Box -->
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-indigo-600 to-blue-600 flex items-center justify-center font-black text-2xl text-white shadow-lg overflow-hidden shrink-0 border-2 border-indigo-500/40">
                            <template x-if="photoPreview">
                                <img :src="photoPreview" alt="Profile Preview" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!photoPreview">
                                @if($profile?->photo_url)
                                    <img src="{{ $profile->photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                                @else
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($profile?->name ?? Auth::user()->name) }}&background=4f46e5&color=ffffff&size=150&bold=true&font-size=0.4" alt="Avatar" class="w-full h-full object-cover">
                                @endif
                            </template>
                        </div>

                        <!-- Upload Inputs -->
                        <div class="flex-1 space-y-2 w-full">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">📸 Upload Photo from Device</label>
                                <input type="file" name="photo" accept="image/*"
                                       @change="if ($event.target.files.length) { photoPreview = URL.createObjectURL($event.target.files[0]); }"
                                       class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer bg-slate-950 border border-white/10 rounded-xl p-1.5">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 mb-1">Or Paste Image URL (Optional)</label>
                                <input type="url" name="photo_url" x-model="photoPreview" placeholder="https://images.unsplash.com/..."
                                       class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bio -->
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1.5">Umpiring Bio & Notable Tournaments</label>
                    <textarea name="bio" rows="3" placeholder="Mention key leagues officiated, expertise in MCC laws of cricket, DRS or live scoring awareness..."
                              class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">{{ old('bio', $profile?->bio) }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                    <a href="{{ route('umpires.index') }}" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 font-bold text-xs transition">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-extrabold text-sm shadow-xl shadow-indigo-600/30 transition transform active:scale-95">
                        Save Umpire Profile ⚖️
                    </button>
                </div>

            </form>

        </div>

    </div>
</div>
@endsection
