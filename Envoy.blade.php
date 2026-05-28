@servers(['web' => 'calm-cliff'])

@setup
    $path = '2026-05-26-talk.antihq.com/';
    $branch = 'main';
@endsetup

@story('deploy')
    maintenance-on
    pull-code
    install-composer
    install-npm
    build-assets
    run-migrations
    optimize
    reload-phpfpm
    maintenance-off
@endstory

@task('maintenance-on', ['on' => 'web'])
    cd {{ $path }}
    php artisan down --retry=60
@endtask

@task('pull-code', ['on' => 'web'])
    cd {{ $path }}
    git pull origin {{ $branch }}
@endtask

@task('install-composer', ['on' => 'web'])
    cd {{ $path }}
    composer install --no-dev --optimize-autoloader --no-interaction
@endtask

@task('install-npm', ['on' => 'web'])
    cd {{ $path }}
    npm ci --no-audit --no-fund
@endtask

@task('build-assets', ['on' => 'web'])
    cd {{ $path }}
    npm run build
@endtask

@task('run-migrations', ['on' => 'web'])
    cd {{ $path }}
    php artisan migrate --force
@endtask

@task('optimize', ['on' => 'web'])
    cd {{ $path }}
    php artisan optimize
@endtask

@task('reload-phpfpm', ['on' => 'web'])
    touch /tmp/fpmlock 2>/dev/null || true
    ( flock -w 10 9 || exit 1
        sudo service php8.5-fpm reload ) 9>/tmp/fpmlock
@endtask

@task('maintenance-off', ['on' => 'web'])
    cd {{ $path }}
    php artisan up
@endtask
