const gulp = require( 'gulp' );
const $ = require( 'gulp-load-plugins' )();
const webpack = require( 'webpack-stream' );
const webpackBundle = require( 'webpack' );
const named = require( 'vinyl-named' );
const { dumpSetting } = require('@kunoichi/grab-deps');

let plumber = true;

// Package jsx.
gulp.task( 'jsx', function () {
	return gulp.src( [
		'./assets/js/**/*.js',
	] )
		.pipe( $.plumber( {
			errorHandler: $.notify.onError( '<%= error.message %>' )
		} ) )
		.pipe( named( (file) =>  {
			return file.relative.replace(/\.[^\.]+$/, '');
		} ) )
		.pipe( webpack( require( './webpack.config.js' ), webpackBundle ) )
		.pipe( gulp.dest( './dist/js' ) );
} );

// Dump dependencies.
gulp.task( 'dump', ( done ) => {
	dumpSetting( 'dist' );
	done();
} );

// Default Tasks
gulp.task( 'default', gulp.series( 'jsx', 'dump' ) );
