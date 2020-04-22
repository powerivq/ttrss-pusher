const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
  module: {
    rules: [
      {
        test: /\.ts$/,
        use: 'ts-loader',
        exclude: ['/node_modules/'],
      },
    ],
  },
  devtool: 'source-map',
  resolve: {
    extensions: ['.ts'],
  },
  entry: {
    main: './ts/main.ts',
    worker: './ts/worker.ts',
  },
  output: {
    filename: '[name].js',
    path: __dirname + '/dist/pusher',
  },
  plugins: [
    new CopyWebpackPlugin([{from: 'vendor', to: 'vendor'}, {from: 'php'}]),
  ],
};
