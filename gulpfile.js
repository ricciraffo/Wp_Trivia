var gulp = require('gulp');
var minifyCss = require('gulp-clean-css');
var ext = require('gulp-ext-replace');
var uglify = require('gulp-uglify');

var paths = {
    css: [
        'css/wpTrivia_front.css'
    ],
    js: [
        'js/wpTrivia_admin.js',
        'js/wpTrivia_front.js',
        'js/wpTrivia_toplist.js'
    ]
};

gulp.task('css-task', function() {
    return gulp
        .src(paths.css)
        .pipe(ext('min.css'))
        .pipe(minifyCss({
            compatibility: 'ie7'
        }))
        .pipe(gulp.dest('css'));
});

gulp.task('js-task', function() {
    return gulp
        .src(paths.js)
        .pipe(ext('min.js'))
        .pipe(uglify())
        .pipe(gulp.dest('js'));
});

gulp.task('watch', function() {
    gulp.watch(paths.css, ['css-task']);
    gulp.watch(paths.js, ['js-task']);
});

gulp.task('default', ['css-task', 'js-task']);
