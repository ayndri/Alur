<x-layouts.app :title="$project->key.'-'.$card->number.' '.$card->title" :project="$project">
    @include('cards._detail')
</x-layouts.app>
