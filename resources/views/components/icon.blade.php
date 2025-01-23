<div>
    @switch($name)
        @case('dashboard')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            @break
        @case('users')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            @break
        @case('settings')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.209 0-4 1.791-4 4s1.791 4 4 4 4-1.791 4-4-1.791-4-4-4zM4.929 14.83a1 1 0 00-.364 1.342l.728 1.26a1 1 0 001.342.364l1.132-.654a7.982 7.982 0 002.197 1.268l.173 1.35a1 1 0 001 1h1.45a1 1 0 001-1l.173-1.35a7.982 7.982 0 002.197-1.268l1.132.654a1 1 0 001.342-.364l.728-1.26a1 1 0 00-.364-1.342l-1.132-.654a7.982 7.982 0 000-2.536l1.132-.654a1 1 0 00.364-1.342l-.728-1.26a1 1 0 00-1.342-.364l-1.132.654a7.982 7.982 0 00-2.197-1.268L13 4.04a1 1 0 00-1-1h-1.45a1 1 0 00-1 1l-.173 1.35a7.982 7.982 0 00-2.197 1.268l-1.132-.654a1 1 0 00-1.342.364l-.728 1.26a1 1 0 00.364 1.342l1.132.654a7.982 7.982 0 000 2.536l-1.132.654z" />
            </svg>
            @break
        @case('profile')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            @break
        @case('logout')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 11-4 0v-1m0-10v1a2 2 0 114 0V7" />
            </svg>
            @break
        @case('settlement')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16l-4-4m0 0l4-4m-4 4h16m-7-4v4m0 4v-4m3 0H5m14-3a3 3 0 01-3-3m3 0a3 3 0 013-3" />
            </svg>
            @break
        @case('merchant')
            <svg class="{{ $class }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h4l1 2h8l1-2h4v6H3V6z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12v6h4v-6H5zm10 0v6h4v-6h-4z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3h6v3H9V3z" />
            </svg>

            @break
    @endswitch
</div>
