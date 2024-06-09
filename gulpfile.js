const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const clean = require('gulp-clean');
const path = require('path');

const scssPath = path.join(__dirname, 'scss/**/*.scss');
const cssPath = path.join(__dirname, 'wp-logify/assets/css');
const pluginSourcePath = path.join(__dirname, 'wp-logify/**/*');
const pluginDestPath = path.join(__dirname, 'www/wp-content/plugins/wp-logify');

console.log('scssPath:', scssPath);
console.log('cssPath:', cssPath);
console.log('pluginSourcePath:', pluginSourcePath);
console.log('pluginDestPath:', pluginDestPath);

gulp.task('clean-css', function () {
    return gulp.src(cssPath, { read: false, allowEmpty: true })
        .pipe(clean());
});

gulp.task('sass', function () {
    return gulp.src(scssPath)
        .pipe(sass().on('error', sass.logError))
        .pipe(gulp.dest(cssPath));
});

gulp.task('clean-plugin', function () {
    return gulp.src(pluginDestPath, { read: false, allowEmpty: true })
        .pipe(clean());
});

gulp.task('copy-plugin', function () {
    return gulp.src(pluginSourcePath)
        .pipe(gulp.dest(pluginDestPath));
});

gulp.task('watch', function () {
    gulp.watch(scssPath, gulp.series('clean-css', 'sass'));
    gulp.watch(pluginSourcePath, gulp.series('clean-plugin', 'copy-plugin'));
});

gulp.task('default', gulp.series('clean-css', 'sass', 'clean-plugin', 'copy-plugin', 'watch'));
