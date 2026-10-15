{{-- partials/timetable-grid.blade.php --}}
<div class="overflow-x-auto mb-6">
    <div class="min-w-full inline-block align-middle">
        <table class="min-w-full border-2 border-gray-300 rounded-lg">
            <thead class="bg-gradient-to-r from-gray-100 to-gray-200">
                <tr>
                    <th class="border-2 border-gray-300 px-4 py-3 text-center font-bold text-gray-700 whitespace-nowrap">
                        <div class="text-sm">Time →<br>Day ↓</div>
                    </th>
                    @foreach($timeSlots as $timeSlot)
                        <th class="border-2 border-gray-300 px-4 py-3 text-center font-semibold text-gray-700 whitespace-nowrap">
                            <div class="text-xs">
                                <span class="mb-1 block font-extrabold {{ $timeSlot->is_break ? 'text-amber-700' : 'text-slate-800' }}">{{ $timeSlot->label ?: 'Lesson' }}</span>
                                {{ date('g:i', strtotime($timeSlot->start_time)) }}<br>–<br>{{ date('g:i', strtotime($timeSlot->stop_time)) }}
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($weekdays as $weekday)
                    <tr>
                        <td class="border-2 border-gray-300 px-4 py-3 font-semibold text-gray-800 bg-gray-50 whitespace-nowrap">
                            {{ $weekday->name }}
                        </td>
                        @foreach($timeSlots as $timeSlot)
                            <td class="border-2 border-gray-300 px-3 py-3 {{ $timeSlot->is_break ? 'bg-amber-50' : ($canUpdateTimetable ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default bg-gray-50/40') }} transition"
                                @if($canUpdateTimetable && !$timeSlot->is_break)
                                    wire:click="selectCell({{ $timeSlot->id }}, {{ $weekday->id }})"
                                @endif>
                                @php
                                    $pivot = $timeSlot->weekdays->find($weekday->id)?->timetableRecord;
                                @endphp

                                @if($timeSlot->is_break)
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-700"><i class="fas fa-mug-hot"></i>{{ $timeSlot->label }}</span>
                                @elseif($pivot)
                                    @if($pivot->timetable_time_slot_weekdayable_type === "App\\Models\\Subject")
                                        <span class="inline-flex px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-semibold">
                                            {{ $subjects->find($pivot->timetable_time_slot_weekdayable_id)?->name }}
                                        </span>
                                    @elseif($pivot->timetable_time_slot_weekdayable_type === "App\\Models\\CustomTimetableItem")
                                        <span class="inline-flex px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-semibold">
                                            {{ $customItems->find($pivot->timetable_time_slot_weekdayable_id)?->name }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-gray-400 text-xs">Click to assign</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
