@props(['backgroundColour' =>  'bg-blue-700', 'textColour' => 'text-white','title' => '', 'footer' => '', 'icon' => '', 'buttonText' => 'Delete', 'button', 'size' => 'base', 'modalButtonClass' => ''])

<div @keydown.escape.window="modal = false" x-data="{modal : false}" class="{{$textColour}}">
    
    @isset($button)
        {{$button}}
    @else
        <x-button class="{{$backgroundColour}} {{$modalButtonClass}} rounded" icon="{{$icon}}" @click="modal = true">
            {{$buttonText}}
        </x-button>
    @endisset

    @php
        switch ($size) {
            case 'base':
                $sizeClass = "h-[60%] w-11/12 md:w-10/12 lg:w-8/12 xl:w-6/12";
                break;
            case 'lg':
                $sizeClass = "h-[90%] w-11/12 lg:w-9/12";
            default:
                $sizeClass = "h-[90%] w-11/12 lg:w-9/12";
                break;
        }
    @endphp

    <div class=" w-screen h-screen fixed inset-0 z-50 bg-black bg-opacity-70 flex items-center justify-center" @click="modal = false" x-show="modal" style="display: none" x-transition {{$attributes}}>
        <div role="dialog" aria-modal="true" aria-label="{{$title}}" class="dashboard-shared-modal {{$sizeClass}} flex justify-between flex-col bg-white dark:bg-gray-900 rounded-xl border" @click.stop>
            <div class="dashboard-shared-modal-header {{$backgroundColour}} h-16 md:h-20 rounded-t-xl flex justify-between  items-center p-4">
                <div class="flex gap-4 overflow-y-scroll beautify-scrollbar">
                    <i class="{{$icon}} text-2xl" aria-hidden="true" ></i>
                    <h4 class="text-2xl font-semibold">{{$title}}</h4>
                </div>
                <button type="button" class="dashboard-icon-button" @click="modal = false" aria-label="Close dialog"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="dashboard-shared-modal-body flex justify-center items-center flex-col overflow-scroll beautify-scrollbar text-black dark:text-white">
                {{$slot}}
            </div>
            <div class="dashboard-shared-modal-footer border-t h-16 md:h-20 flex justify-between items-center p-4">
                <x-button label="Close" class="bg-gray-600 text-white" @click="modal = false"/>
                <div>
                    {{$footer}}
                </div>
            </div>
        </div>
    </div>
</div>