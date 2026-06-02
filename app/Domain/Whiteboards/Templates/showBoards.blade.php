@extends($layout)

@section('content')

@php
    $whiteboards = $whiteboards ?? [];
    $canEdit = $canEdit ?? false;
@endphp

<div class="pageheader">
    <div class="pageicon"><i class="fa-solid fa-chalkboard"></i></div>
    <div class="pagetitle">
        <h5>{{ session('currentProjectClient') . ' // ' . session('currentProjectName') }}</h5>
        <h1>{!! __('headlines.whiteboards') !!}</h1>
    </div>
</div><!--pageheader-->

<div class="maincontent">
    <div class="maincontentinner" id="whiteboardList" style="min-height:350px;">
        {!! $tpl->displayNotification() !!}

        @if ($canEdit)
            <div class="row" style="margin-bottom:20px;">
                <div class="col-md-12">
                    <a href="#newWhiteboardModal" data-toggle="modal" class="btn btn-primary">
                        <span class="fa fa-plus"></span> {{ __('buttons.new_whiteboard') }}
                    </a>
                </div>
            </div>
        @endif

        <div class="clearfix"></div>

        @if (count($whiteboards) > 0)
            <div class="row">
                @foreach ($whiteboards as $board)
                    <div class="col-md-3 col-sm-6">
                        <div class="ticketBox" id="whiteboard_{{ $board['id'] }}" style="position:relative;">
                            @if ($canEdit)
                                <div class="inlineDropDownContainer" style="position:absolute; top:10px; right:10px;">
                                    <a href="javascript:void(0);" class="dropdown-toggle ticketDropDown" data-toggle="dropdown">
                                        <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                    </a>
                                    <ul class="dropdown-menu pull-right">
                                        <li><a href="{{ BASE_URL }}/whiteboards/show/{{ $board['id'] }}"><i class="fa fa-pen-to-square fa-fw"></i> {{ __('links.open') }}</a></li>
                                        <li><a href="{{ BASE_URL }}/whiteboards/delete/{{ $board['id'] }}" class="delete"><i class="fa fa-trash fa-fw"></i> {{ __('links.delete') }}</a></li>
                                    </ul>
                                </div>
                            @endif

                            <a href="{{ BASE_URL }}/whiteboards/show/{{ $board['id'] }}" style="text-decoration:none; color:inherit;">
                                <div style="display:flex; align-items:center; justify-content:center; height:120px; background:var(--secondary-background); border-radius:var(--box-radius-small); margin-bottom:12px;">
                                    <i class="fa-solid fa-chalkboard" style="font-size:42px; color:var(--accent1);"></i>
                                </div>
                                <h4 style="margin:0 0 5px 0;">{{ $tpl->escape($board['title']) }}</h4>
                                <div class="tw-text-sm" style="color:var(--lighter-font-color); font-size:var(--font-size-xs);">
                                    {{ trim(($board['firstname'] ?? '') . ' ' . ($board['lastname'] ?? '')) }}
                                    @if (!empty($board['modified']))
                                        &middot; {{ format($board['modified'])->date() }}
                                    @endif
                                </div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="row">
                <div class="col-md-12">
                    <div class="emptyState" style="text-align:center; padding:60px 20px;">
                        <i class="fa-solid fa-chalkboard" style="font-size:64px; color:var(--accent1); opacity:.5;"></i>
                        <h3 style="margin-top:20px;">{{ __('headlines.whiteboards_empty') }}</h3>
                        <p style="color:var(--lighter-font-color);">{{ __('text.whiteboards_empty') }}</p>
                        @if ($canEdit)
                            <a href="#newWhiteboardModal" data-toggle="modal" class="btn btn-primary" style="margin-top:10px;">
                                <span class="fa fa-plus"></span> {{ __('buttons.new_whiteboard') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

@if ($canEdit)
    {{-- New whiteboard modal --}}
    <div class="modal fade" id="newWhiteboardModal" tabindex="-1" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="{{ BASE_URL }}/whiteboards/showBoards">
                    @csrf
                    <input type="hidden" name="newBoard" value="1" />
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title">{{ __('headlines.new_whiteboard') }}</h4>
                    </div>
                    <div class="modal-body">
                        <label for="newWhiteboardTitle">{{ __('label.whiteboard_title') }}</label>
                        <input type="text" id="newWhiteboardTitle" name="title" maxlength="255" required
                               placeholder="{{ __('label.whiteboard_title') }}" style="width:100%;" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('buttons.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('buttons.new_whiteboard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            jQuery(function () {
                jQuery('#newWhiteboardModal').on('shown shown.bs.modal', function () {
                    jQuery(this).find('input[name=title]').trigger('focus');
                });
            });
        </script>
    @endpush
@endif

@endsection
