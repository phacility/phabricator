# GitHub Octicons

[![NPM version](https://img.shields.io/npm/v/octicons.svg)](https://www.npmjs.org/package/octicons)
[![Build Status](https://travis-ci.org/primer/octicons.svg?branch=master)](https://travis-ci.org/primer/octicons)

> Octicons are a scalable set of icons handcrafted with <3 by GitHub.

## Install

**NOTE:** The compiled files are located in `/build/`. This directory is located in the published npm package. Which means you can access it when you `npm install octicons`. You can also build this directory by following the [building octicons directions](#building-octicons). The files in the `/lib/` directory are the raw source files and are not compiled or optimized.

#### NPM

This repository is distributed with [npm][npm]. After [installing npm][install-npm], you can install `octicons` with this command.

```
$ npm install --save octicons
```

## Usage

For all the usages, we recommend using the CSS located in `./build/octicons.css`. This is some simple CSS to normalize the icons and inherit colors.

### Spritesheet

With a [SVG sprite icon system](https://css-tricks.com/svg-sprites-use-better-icon-fonts/) you can include the sprite sheet located `./build/sprite.octicons.svg` after you [build the icons](#building-octicons) or from the npm package. There is a demo of how to use the spritesheet in the build directory also.

### Node

After installing `npm install octicons` you can access the icons like this.

```js
var octicons = require("octicons")
octicons.alert
// { keywords: [ 'warning', 'triangle', 'exclamation', 'point' ],
//   path: '<path d="M8.865 1.52c-.18-.31-.51-.5-.87-.5s-.69.19-.87.5L.275 13.5c-.18.31-.18.69 0 1 .19.31.52.5.87.5h13.7c.36 0 .69-.19.86-.5.17-.31.18-.69.01-1L8.865 1.52zM8.995 13h-2v-2h2v2zm0-3h-2V6h2v4z"/>',
//   height: '16',
//   width: '16',
//   symbol: 'alert',
//   options:
//    { version: '1.1',
//      width: '16',
//      height: '16',
//      viewBox: '0 0 16 16',
//      class: 'octicon octicon-alert',
//      'aria-hidden': 'true' },
//   toSVG: [Function] }
```

There will be a key for every icon, with `keywords` and `svg`.

#### `octicons.alert.symbol`

Returns the string of the symbol name

```js
octicons.x.symbol
// "x"
```

#### `octicons.person.path`

Path returns the string representation of the path of the icon.

```js
octicons.x.path
// <path d="M7.48 8l3.75 3.75-1.48 1.48L6 9.48l-3.75 3.75-1.48-1.48L4.52 8 .77 4.25l1.48-1.48L6 6.52l3.75-3.75 1.48 1.48z"></path>
```

#### `octicons.issue.options`

This is a json object of all the `options` that will be added to the output tag.

```js
octicons.x.options
// { version: '1.1', width: '12', height: '16', viewBox: '0 0 12 16', class: 'octicon octicon-x', 'aria-hidden': 'true' }
```

#### `octicons.alert.width`

Width is the icon's true width. Based on the svg view box width. _Note, this doesn't change if you scale it up with size options, it only is the natural width of the icon_

#### `octicons.alert.height`

Height is the icon's true height. Based on the svg view box height. _Note, this doesn't change if you scale it up with size options, it only is the natural height of the icon_

#### `keywords`

Returns an array of keywords for the icon. The data [comes from the octicons repository](https://github.com/primer/octicons/blob/master/lib/data.json). Consider contributing more aliases for the icons.

```js
octicons.x.keywords
// ["remove", "close", "delete"]
```

#### `octicons.alert.toSVG()`

Returns a string of the svg tag

```js
octicons.x.toSVG()
// <svg version="1.1" width="12" height="16" viewBox="0 0 12 16" class="octicon octicon-x" aria-hidden="true"><path d="M7.48 8l3.75 3.75-1.48 1.48L6 9.48l-3.75 3.75-1.48-1.48L4.52 8 .77 4.25l1.48-1.48L6 6.52l3.75-3.75 1.48 1.48z"/></svg>
```

The `.toSVG()` method accepts an optional `options` object. This is used to add CSS classnames, a11y options, and sizing.

##### class

Add more CSS classes to the `<svg>` tag.

```js
octicons.x.toSVG({ "class": "close" })
// <svg version="1.1" width="12" height="16" viewBox="0 0 12 16" class="octicon octicon-x close" aria-hidden="true"><path d="M7.48 8l3.75 3.75-1.48 1.48L6 9.48l-3.75 3.75-1.48-1.48L4.52 8 .77 4.25l1.48-1.48L6 6.52l3.75-3.75 1.48 1.48z"/></svg>
```

##### aria-label

Add accessibility `aria-label` to the icon.

```js
octicons.x.toSVG({ "aria-label": "Close the window" })
// <svg version="1.1" width="12" height="16" viewBox="0 0 12 16" class="octicon octicon-x" aria-label="Close the window" role="img"><path d="M7.48 8l3.75 3.75-1.48 1.48L6 9.48l-3.75 3.75-1.48-1.48L4.52 8 .77 4.25l1.48-1.48L6 6.52l3.75-3.75 1.48 1.48z"/></svg>
```

##### width & height

Size the SVG icon larger using `width` & `height` independently or together.

```js
octicons.x.toSVG({ "width": 45 })
// <svg version="1.1" width="45" height="60" viewBox="0 0 12 16" class="octicon octicon-x" aria-hidden="true"><path d="M7.48 8l3.75 3.75-1.48 1.48L6 9.48l-3.75 3.75-1.48-1.48L4.52 8 .77 4.25l1.48-1.48L6 6.52l3.75-3.75 1.48 1.48z"/></svg>
```

#### `octicons.alert.toSVGUse()`

Returns a string of the svg tag with the `<use>` tag, for use with the spritesheet located in the /build/ directory.

```js
octicons.x.toSVGUse()
// <svg version="1.1" width="12" height="16" viewBox="0 0 12 16" class="octicon octicon-x" aria-hidden="true"><use xlink:href="#x" /></svg>
```

### Ruby

If your environment is Ruby on Rails, we have a [octicons_helper](https://github.com/primer/octicons_helper) gem available that renders SVG in your page. The octicons_helper uses the [octicons_gem](https://github.com/primer/octicons_gem) to do the computing and reading of the SVG files.

### Jekyll

For jekyll, there's a [jekyll-octicons](https://github.com/primer/jekyll-octicons) plugin available. This works exactly like the octicons_helper.

## Changing, adding, or deleting icons

1. Open the [Sketch document][sketch-document] in `/lib/`. Each icon exists as an artboard within our master Sketch document. If you’re adding an icon, duplicate one of the artboards and add your shapes to it. Be sure to give your artboard a name that makes sense.
2. Once you’re happy with your icon set, choose File > Export…
3. Choose all the artboards you’d like to export and then press “Export”
4. Export to `/lib/svg/`

You’ll next need to build your Octicons.

## Building Octicons

All the files you need will be in the `/build/` directory already, but if you’ve made changes to the `/lib/` directory and need to regenerate, follow these steps:

1. Open the Octicons directory in Terminal
2. `npm install` to install all dependencies for the project.
3. Run the command `npm run build`. This will run the grunt task to build the SVGs, placing them in the `/build/` directory.

## Publishing

If you have access to publish this repository, these are the steps to publishing. If you need access, contact [#design-systems](https://github.slack.com/archives/design-systems).

1. Update the [CHANGELOG.md](./CHANGELOG.md) with relevant version number and any updates made to the repository.
2. `npm version <newversion>` Run [npm version](https://docs.npmjs.com/cli/version) inputing the relevant version type. The versioning is [semver](http://semver.org/), so version appropriately based on what has changed.
3. `npm publish` This will publish the new version to npmjs.org
4. `git push && git push --tags` Push all these changes to origin.

## License

(c) 2012-2016 GitHub, Inc.

When using the GitHub logos, be sure to follow the [GitHub logo guidelines](https://github.com/logos).

_SVG License:_ [SIL OFL 1.1](http://scripts.sil.org/OFL)  
Applies to all SVG files

_Code License:_ [MIT](./LICENSE)  
Applies to all other files

[primer]: https://github.com/primer/primer
[docs]: http://primercss.io/
[npm]: https://www.npmjs.com/
[install-npm]: https://docs.npmjs.com/getting-started/installing-node
[sass]: http://sass-lang.com/
[sketch-document]: https://github.com/primer/octicons/blob/master/lib/octicons-master.sketch
