import gulp from 'gulp';
import gulpSass from 'gulp-sass';
import * as sass from 'sass'
import path from 'path';
import { fileURLToPath } from 'url';

// Emulate __dirname for ES Modules.
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Paths.
const scssPath = path.join(__dirname, 'scss/**/*.scss');
const cssPath = path.join(__dirname, 'wp-logify/assets/css');

// Debug.
console.log('scssPath:', scssPath);
console.log('cssPath:', cssPath);

// Setup gulp-sass.
const sassProcessor = gulpSass(sass);

// Sass task.
gulp.task('sass', function () {
    return gulp.src(scssPath)
        .pipe(sassProcessor().on('error', sassProcessor.logError))
        .pipe(gulp.dest(cssPath));
});

// Watch task.
gulp.task('watch', function () {
    gulp.watch(scssPath, { usePolling: true }, gulp.series('sass'));
});

gulp.task('default', gulp.series('sass', 'watch'));
