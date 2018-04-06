# Shopp to WooCommerce Migrations

This WP-CLI package is designed to migrate product catalogs from [Shopp](https://shopplugin.net/) into [WooCommerce](http://woocommerce.com/).

[![Build Status](https://travis-ci.org/liquidweb/shopp-to-woocommerce.svg?branch=develop)](https://travis-ci.org/liquidweb/shopp-to-woocommerce)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

> **Note:** This package does not cover every aspect of Shopp to WooCommerce migrations, and comes with no warranty. Always test on staging, back up your store before running, and use at your own risk!

To migrate products and taxonomy terms from Shopp to WooCommerce:

```sh
$ wp shopp-to-woocommerce migrate
```

The `migrate` command is an all-in-one process, which will perform the following tasks:

1. Ensure both Shopp and WooCommerce are installed and active
2. Empty WordPress' trash, ensuring cycles aren't wasted moving deleted content.
3. Analyze the site content to determine what requires migration.
4. Migrate Shopp taxonomy terms (categories and tags) into WooCommerce taxonomies.
5. Migrate the product catalog, including images, variants, and more!

Each of these steps are also available to run individually, if necessary. Please run `wp shopp-to-woocommerce --help` to see a list of all available commands.

## Installing

Installing this package requires WP-CLI v1.1.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:liquidweb/shopp-to-woocommerce.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/liquidweb/shopp-to-woocommerce/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/liquidweb/shopp-to-woocommerce/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/liquidweb/shopp-to-woocommerce/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support
