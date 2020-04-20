const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
  module: {
    rules: [
      {
        test: /\.js$/,
        use: 'babel-loader',
      },
    ],
  },
  entry: {
    main: './js/main.js',
    worker: './js/worker.js',
  },
  output: {
    filename: '[name].js',
    path: __dirname + '/dist/pusher',
  },
  plugins: [
    new CopyWebpackPlugin([
      {from: 'vendor', to: 'vendor'},
      {from: 'php'},
    ]),
  ],
};
