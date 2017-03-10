# How does the CSV import work?

Ok, let's do this. Maybe it'll end up as a blog post someday.

First thing is you open up the `/upload/` page, let's say we're using [the dev server](https://whosonfirst.dev.mapzen.com/boundaryissues/upload/). You start by clicking on the "Choose CSV File" button, and find the file you're going to upload.

Once that happens, some JavaScript comes along and inspects the file you've chosen. We're using the [Papa Parse](http://papaparse.com/) library to pull out some information about the CSV. Mostly we're just checking that first column, assuming that it has column headers (a task still left to be done is making the column headers optional).

Once we have the column headers, we present it to the end user in a big list. Each column can get mapped onto a WOF property. You could say the `name` CSV columns should get stored under the `wof:name` property. Once you've chosen the mappings you like (and you meet the minimum name/place requirements), you upload the file and get dropped into the first venue import page.

A venue import is deceptively simple. On the page all you see are three fields (name, address, tags), and a map. But there's a lot going on behind the scenes, in JavaScript-land. At a high level, what you should know is that when you press the save button, we're generating a GeoJSON string and then using AJAX to pass it to the Boundary Issues `wof.save` API method.

The way that GeoJSON feature gets generated depends a bit on whether the CSV row has _already_ been imported, or if we're starting fresh. In the latter case, we by copying each column value into the right properties, and then overriding those values with the name/address/tags/map location.

If this is a CSV row that has already been imported (or if we have a `wof_id` column matching against a known WOF record), then we load up _that_ previously-imported GeoJSON file as our starting point. Then we override it with the chosen column-to-property assignments, and finally override with name/address/tags/map location.
