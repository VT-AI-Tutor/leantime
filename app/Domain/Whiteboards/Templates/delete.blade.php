@extends($layout)

@section('content')

<div class="pageheader">
    <div class="pageicon"><i class="fa-solid fa-chalkboard"></i></div>
    <div class="pagetitle">
        <h5>{{ session('currentProjectClient') . ' // ' . session('currentProjectName') }}</h5>
        <h1>{!! __('headlines.whiteboards') !!}</h1>
    </div>
</div>

<div class="maincontent">
    <div class="maincontentinner">
        <h4 class="widgettitle title-light">{!! __('subtitles.delete') !!}</h4>

        <form method="post" action="{{ BASE_URL }}/whiteboards/delete/{{ $board['id'] }}">
            @csrf
            <input type="hidden" name="id" value="{{ $board['id'] }}" />
            <p>{{ __('text.confirm_whiteboard_deletion') }} <strong>{{ $tpl->escape($board['title']) }}</strong></p>
            <br />
            <input type="submit" value="{{ __('buttons.yes_delete') }}" name="del" class="btn btn-primary" />
            <a class="btn btn-secondary" href="{{ BASE_URL }}/whiteboards/showBoards">{{ __('buttons.back') }}</a>
        </form>
    </div>
</div>

@endsection
