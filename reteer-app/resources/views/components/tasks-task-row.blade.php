@props(['task'])
<div>
    <div class="hover:bg-orange-50 hover:border hover:rounded-xl flex flex-row flex-wrap">
        <button id="raise-{{ $task->id }}" class="w-1/6 border border-gray-200 h-auto m-1 rounded-xl">Sign
            Up</button>
        <button class="w-4/6 h-full flex-1">
            <x-task-detail :task="$task"></x-task-detail>
        </button>
        <button class="w-auto border border-gray-200 p-2 h-auto m-1 mx-3 rounded-xl">View<br>Details</button>
    </div>
</div>
