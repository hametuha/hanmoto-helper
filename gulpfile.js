var gulp        = require('gulp'),
    fs          = require('fs'),
    $           = require('gulp-load-plugins')(),
    pngquant    = require('imagemin-pngquant'),
    eventStream = require('event-stream');

// Sass task
gulp.task('sass',function(){
  return gulp.src(['./src/scss/**/*.scss'])
    .pipe($.plumber({
      errorHandler: $.notify.onError('<%= error.message %>')
    }))
    .pipe($.sassBulkImport())
    .pipe($.sass({
      errLogToConsole: true,
      outputStyle: 'compressed',
      sourceComments: 'normal',
      sourcemap: true,
      includePaths: [
        './src/scss'
      ]
    }))
    .pipe( $.autoprefixer({
      browsers: ['last 2 versions']
    }) )
    .pipe(gulp.dest('./assets/css'));
});


// Image min
gulp.task('imagemin', function(){
  return gulp.src('./src/img/**/*')
    .pipe($.imagemin({
      progressive: true,
      svgoPlugins: [{removeViewBox: false}],
      use: [pngquant()]
    }))
    .pipe(gulp.dest('./assets/img'));
});

// watch
gulp.task('watch',function(){
  // Make SASS
  gulp.watch('./src/scss/**/*.scss',['sass']);
  // Minify Image
  gulp.watch('./src/img/**/*',['imagemin']);
});

// Build
gulp.task('build', ['sass', 'imagemin']);

// Default Tasks
gulp.task('default', ['watch']);
