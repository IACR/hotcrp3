# Design of lncseditor

The lncseditor.php is designed to fulfill the need of organizing papers into
topics for instances that use LNCS (namely Crypto, eurocrypt, asiacrypt, tcc,
and pkc). The organization into volumes is currently done by Springer, so I
have disabled that feature and only show a single volume.

Papers initially start out in the uncategorized section. The editor allows the
chair to create topics and drop the papers into topics. At present all topics
reside within a single volume, but Springer will split any volumes that are over
900 pages. Papers and topics may be reordered by dragging them.

The structure of a proceedings is kept as a JSON object in
`filestore/lncs_userid.json`, and that is updated each time the object is
changed in the UI. The user can also discard their work and start over
again. The structure of the stored json object is described below.

```
year
venue (asiacrypt, crypto, eurocrypt, tcc, pkc)
unassigned_papers (array of paper)
volumes (array, currently only one element)
   topics (array)
      name
      papers (array of paper)
```
A paper object has the form:
```
paperId (from HotCrp)
doi
title
keywords
abstract (optional, but we have it)
pages (number of pages in the final PDF)
authors (array of objects)
   name
   lastName
   affiliation(s) as a string
   orcidid
   email
```
