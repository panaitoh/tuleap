#!/usr/bin/env php
<?php
#
# Copyright (c) Enalean, 2015 - 2017. All rights reserved
#
# This file is a part of Tuleap.
#
# Tuleap is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# Tuleap is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Tuleap. If not, see <http://www.gnu.org/licenses/
#

$basedir = $argv[1];

function info($message)
{
    echo "\033[32m$message\033[0m\n";
}

function warning($message)
{
    echo "\033[33m$message\033[0m\n";
}

info("[core] Generating .pot file");
$core_src = escapeshellarg("$basedir/src");
$template = escapeshellarg("$basedir/site-content/tuleap-core.pot");
exec("find $core_src -name '*.php' \
    | grep -v -E '(common/wiki/phpwiki|common/include/lib|vendor)' \
    | xargs xgettext \
        --default-domain=core \
        --from-code=UTF-8 \
        --no-location \
        --sort-output \
        --omit-header \
        -o - \
    | sed '/^msgctxt/d' \
    > $template");

info("[core] Merging .pot file into .po files");
$site_content = escapeshellarg("$basedir/site-content");
exec("find $site_content -name 'tuleap-core.po' -exec msgmerge --update \"{}\" $template \;");

foreach (glob("$basedir/plugins/*", GLOB_ONLYDIR) as $path) {
    $translated_plugin = basename($path);
    if (! is_file("$path/site-content/tuleap-$translated_plugin.pot")) {
        warning("[$translated_plugin] No .pot file found.");
        continue;
    }

    info("[$translated_plugin] Generating default .pot file");
    $src      = escapeshellarg("$path/include");
    $template = escapeshellarg("$path/site-content/tuleap-$translated_plugin.pot");
    $default  = escapeshellarg("$path/site-content/tuleap-$translated_plugin-default.pot");
    $plural   = escapeshellarg("$path/site-content/tuleap-$translated_plugin-plural.pot");
    exec("find $src -name '*.php' \
        | xargs xgettext \
            --keyword='dgettext:1c,2' \
            --default-domain=$translated_plugin \
            --from-code=UTF-8 \
            --omit-header \
            -o - \
        | msggrep \
            --msgctxt \
            --regexp=$translated_plugin \
            - \
        | sed '/^msgctxt/d' \
        > $default");

    info("[$translated_plugin] Generating plural .pot file");
    exec("find $src -name '*.php' \
        | xargs xgettext \
            --keyword='dngettext:1c,2,3' \
            --default-domain=$translated_plugin \
            --from-code=UTF-8 \
            --omit-header \
            -o - \
        | msggrep \
            --msgctxt \
            --regexp=$translated_plugin \
            - \
        | sed '/^msgctxt/d' \
        > $plural");

    info("[$translated_plugin] Combining .pot files into one");
    exec("msgcat --no-location --sort-output --use-first $plural $default > $template");
    unlink("$path/site-content/tuleap-$translated_plugin-default.pot");
    unlink("$path/site-content/tuleap-$translated_plugin-plural.pot");

    foreach (glob("$path/site-content/*", GLOB_ONLYDIR) as $foreign_dir) {
        if (basename($foreign_dir) === 'en_US') {
            continue;
        }

        $lc_messages = "$foreign_dir/LC_MESSAGES";
        if (! is_dir($lc_messages)) {
            $po_file = escapeshellarg("$lc_messages/tuleap-$translated_plugin.po");
            info("[$translated_plugin] Creating missing $po_file");
            mkdir($lc_messages, 0755, true);
            $content = <<<EOS
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
EOS;

            file_put_contents($po_file, $content);
        }
    }

    info("[$translated_plugin] Merging .pot file into .po files");
    $site_content = escapeshellarg("$path/site-content");
    exec("find $site_content -name 'tuleap-$translated_plugin.po' -exec msgmerge --update \"{}\" $template \;");

    $manifest = "$path/build-manifest.json";
    if (is_file($manifest)) {
        $json = json_decode(file_get_contents($manifest), true);
        if (isset($json['gettext-js']) && is_array($json['gettext-js'])) {
            foreach ($json['gettext-js'] as $component => $gettext) {
                info("[$translated_plugin][js][$component] Generating default .pot file");
                $src      = escapeshellarg("$path/${gettext['src']}");
                $po       = escapeshellarg("$path/${gettext['po']}");
                $template = escapeshellarg("$path/${gettext['po']}/template.pot");
                exec("find $src -name '*.js' \
                    | xargs xgettext \
                        --default-domain=core \
                        --from-code=UTF-8 \
                        --no-location \
                        --sort-output \
                        --omit-header \
                        -o - \
                    | sed '/^msgctxt/d' \
                    > $template");

                info("[$translated_plugin][js][$component] Merging .pot file into .po files");
                exec("find $po -name '*.po' -exec msgmerge --update \"{}\" $template \;");
            }
        }
    }
}
