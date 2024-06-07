const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const path = require('path');
const { cwd } = require('process');

const scssPath = path.join(__dirname, '../src/**/*.scss');
const cssPath = path.join(__dirname, '../../www/wp-content/plugins/wp-logify/assets/css');

console.log('scssPath:', scssPath);
console.log('cssPath:', cssPath);

gulp.task('sass', function () {
    return gulp.src(scssPath)
        .pipe(sass().on('error', sass.logError))
        .pipe(gulp.dest(cssPath));
});

gulp.task('watch', function () {
    gulp.watch(scssPath, gulp.series('sass'));
});

gulp.task('default', gulp.series('sass', 'watch'));
