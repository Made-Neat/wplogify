import gulp from 'gulp';
import gulpSass from 'gulp-sass';
import * as sass from 'sass';
import path from 'path';
import { fileURLToPath } from 'url';

// Emulate __dirname for ES Modules.
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Paths.
const scssPath = path.join(__dirname, 'scss/*.scss');
const cssPath = path.join(__dirname, 'wp-logify/assets/css');

// Debug.
console.log('scssPath:', scssPath);
console.log('cssPath:', cssPath);

// Setup gulp-sass.
const sassProcessor = gulpSass(sass);

// Sass task.
function sassTask() {
    console.log('Running sass task...');
    return gulp.src(scssPath)
        .pipe(sassProcessor().on('error', sassProcessor.logError))
        .pipe(gulp.dest(cssPath));
}

// Watch task.
function watchTask() {
    console.log('Running watch task...');
    gulp.watch(scssPath, { usePolling: true }, gulp.series(sassTask))
        .on('change', function (path, stats) {
            console.log(`File ${path} was changed`);
        })
        .on('add', function (path, stats) {
            console.log(`File ${path} was added`);
        })
        .on('unlink', function (path, stats) {
            console.log(`File ${path} was removed`);
        });
}

// Register tasks.
gulp.task('sass', sassTask);
gulp.task('watch', watchTask);

// Default task.
gulp.task('default', gulp.series('sass', 'watch'));
