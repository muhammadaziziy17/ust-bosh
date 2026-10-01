@extends('layouts.landing')

@section('title', __('landing.brand'))

@section('content')
    @include('landing.partials.hero')
    @include('landing.partials.features')
    @include('landing.partials.steps')
    @include('landing.partials.contact')
@endsection
