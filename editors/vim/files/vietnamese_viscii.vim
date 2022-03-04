" vim:ts=8
"
"   VIQR input
"
"	RFC 1456 Vietnamese Standardization Working Group,
"	Conventions for Encoding the Vietnamese Language
"	VISCII: VIetnamese Standard Code for Information Interchange
"	VIQR: VIetnamese Quoted-Readable Specification Revision 1.1",
"	May 1993.
"
set isprint=@,002,005-006,020,025,128-255
"
"letter �
imap	A'	193
"letter �
imap	A`	192
"letter �
imap	A?	196
"letter �
imap	A~	195
"letter �
imap	A.	128
"letter �
imap	A(	197
"letter �
imap	197'	129
"letter �
imap	197`	130
"letter 
imap	197?	002
"letter 
imap	197~	005
"letter �
imap	197.	131
"letter �
imap	A^	194
"letter �
imap	194'	132
"letter �
imap	194`	133
"letter �
imap	194?	134
"letter 
imap	194~	006
"letter �
imap	194.	135
"letter �
imap	DD	208
imap	Dd	208
"letter �
imap	E'	201
"letter �
imap	E`	200
"letter �
imap	E?	203
"letter �
imap	E~	136
"letter �
imap	E.	137
"letter �
imap	E^	202
"letter �
imap	202'	138
"letter �
imap	202`	139
"letter �
imap	202?	140
"letter �
imap	202~	141
"letter �
imap	202.	142
"letter �
imap	I'	205
"letter �
imap	I`	204
"letter �
imap	I?	155
"letter �
imap	I~	206
"letter �
imap	I.	152
"letter �
imap	O'	211
"letter �
imap	O`	210
"letter �
imap	O?	153
"letter �
"imap	O~	213  -- bug in encoding  213 --> a.
imap	O~	160
"letter �
imap	O.	154
"letter �
imap	O^	212
"letter �
imap	212'	143
"letter �
imap	212`	144
"letter �
imap	212?	145
"letter �
imap	212~	146
"letter �
imap	212.	147
"letter �
imap	O+	180
"letter �
imap	180'	149
"letter �
imap	180`	150
"letter �
imap	180?	151
"letter �
imap	180~	179
"letter �
imap	180.	148
"letter �
imap	U'	218
"letter �
imap	U`	217
"letter �
imap	U?	156
"letter �
imap	U~	157
"letter �
imap	U.	158
"letter �
imap	U+	191
"letter �
imap	191'	186
"letter �
imap	191`	187
"letter �
imap	191?	188
"letter �
imap	191~	255
"letter �
imap	191.	185
"letter �
imap	Y'	221
"letter �
imap	Y`	159
"letter 
imap	Y?	020
"letter 
imap	Y~	025
"letter 
imap	Y.	030
"letter �
imap	a'	225
"letter �
imap	a`	224
"letter �
imap	a?	228
"letter �
imap	a~	227
"letter �
"imap	a.	160  bug in encoding -- 160 --> O~
imap	a.	213
"letter �
imap	a(	229
"letter �
imap	229'	161
"letter �
imap	229`	162
"letter �
imap	229?	198
"letter �
imap	229~	199
"letter �
imap	229.	163
"letter �
imap	a^	226
"letter �
imap	226'	164
"letter �
imap	226`	165
"letter �
imap	226?	166
"letter �
imap	226~	231
"letter �
imap	226.	167
"letter �
imap	dd	240
"letter �
imap	e'	233
"letter �
imap	e`	232
"letter �
imap	e?	235
"letter �
imap	e~	168
"letter �
imap	e.	169
"letter �
imap	e^	234
"letter �
imap	234'	170
"letter �
imap	234`	171
"letter �
imap	234?	172
"letter �
imap	234~	173
"letter �
imap	234.	174
"letter �
imap	i'	237
"letter �
imap	i`	236
"letter �
imap	i?	239
"letter �
imap	i~	238
"letter �
imap	i.	184
"letter �
imap	o'	243
"letter �
imap	o`	242
"letter �
imap	o?	246
"letter �
imap	o~	245
"letter �
imap	o.	247
"letter �
imap	o^	244
"letter �
imap	244'	175
"letter �
imap	244`	176
"letter �
imap	244?	177
"letter �
imap	244~	178
"letter �
imap	244.	181
"letter �
imap	o+	189
"letter �
imap	189'	190
"letter �
imap	189`	182
"letter �
imap	189?	183
"letter �
imap	189~	222
"letter �
imap	189.	254
"letter �
imap	u'	250
"letter �
imap	u`	249
"letter �
imap	u?	252
"letter �
imap	u~	251
"letter �
imap	u.	248
"letter �
imap	u+	223
"letter �
imap	223'	209
"letter �
imap	223`	215
"letter �
imap	223?	216
"letter �
imap	223~	230
"letter �
imap	223.	241
"letter �
imap	y'	253
"letter �
imap	y`	207
"letter �
imap	y?	214
"letter �
imap	y~	219
"letter �
imap	y.	220
	"
	"	END OF VIQR input support
