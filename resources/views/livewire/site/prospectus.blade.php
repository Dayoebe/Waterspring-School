@extends('layouts.app', ['mode' => 'public'])
@section('title', 'School Prospectus')
@section('content')
@php
    $chapters = ['welcome' => 'Welcome', 'about-school' => 'About our school', 'facilities' => 'Facilities', 'mission-vision' => 'Mission & vision', 'ethos' => 'School ethos', 'curriculum' => 'Classes & curriculum', 'school-day' => 'The school day', 'uniforms' => 'Uniforms', 'attendance' => 'Attendance', 'transport' => 'Bus service', 'clubs' => 'Clubs & activities', 'prospectus-contact' => 'Contact the school'];
    $facilities = [
        'ICT Room' => 'A hub of innovation with computers, software and technology. Digital resources help pupils develop coding, programming and multimedia skills.',
        'Art Gallery' => 'A dedicated space for pupils’ artwork, with art materials and supplies that encourage imagination, creativity and self-expression.',
        'Science Room' => 'A place for experiments, scientific discovery and critical thinking, where pupils explore concepts through practical investigations.',
        'School Library' => 'Books and journals in a quiet space for reading, research and learning, nurturing a love of literature and lifelong learning.',
        'Sand Pit and Play Areas' => 'Safe outdoor spaces where children explore, create and develop motor skills through physical activity, social interaction and play.',
        'Music Room' => 'Pianos, drums, guitars and other instruments support individual practice and group performances. Lessons develop creativity, coordination, concentration and collaboration.',
    ];
    $classes = [
        'Early Years & Foundation Stage' => [['Crèche', '0–11 months'], ['Playgroup', '12–24 months'], ['Pre-School 1', '2–3 years'], ['Pre-School 2', '3–4 years'], ['Reception', '4–5 years']],
        'Key Stage 1' => [['Year 1', '5–6 years'], ['Year 2', '6–7 years']],
        'Key Stage 2' => [['Year 3', '7–8 years'], ['Year 4', '8–9 years'], ['Year 5', '9–10 years'], ['Year 6', '10–11 years']],
    ];
    $subjects = ['Literacy', 'Numeracy', 'Science', 'Information and Communication Technology', 'Art and Design', 'Religious Education', 'Music', 'General Knowledge', 'French and Yoruba (Years 1–6)'];
    $timetable = [['7:00–8:15 am', 'Drop-off into school / classroom'], ['8:15–10:15 am', 'Learning session'], ['10:15–10:45 am', 'Snack / break time'], ['10:45 am–1:45 pm', 'Learning session 2'], ['1:45–2:15 pm', 'Lunch'], ['2:15–3:15 pm', 'Extracurricular activities'], ['3:30–5:00 pm', 'Pick-up by parents']];
    $clubs = ['French Club', 'Dance Club', 'Music', 'Mr. Maker', 'Young Authors Club', 'Taekwondo', 'Culinary Club', 'Board Games Club', 'Press Club', 'Young Picasso', 'Sports Club'];
@endphp
<div class="prospectus-page bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['eyebrow' => 'Our school prospectus', 'title' => 'This is where your child belongs', 'description' => 'Get to know Watersprings: our learning community, early years and primary curriculum, and the everyday details that help your family feel at home.'])
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-6 sm:px-6 lg:px-8">
            <p class="max-w-xl text-sm leading-relaxed text-slate-600">Read the school prospectus below, or open the original illustrated PDF.</p>
            <a href="{{ $publicSiteSettings['prospectus_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-xl bg-sky-800 px-5 py-3 text-sm font-bold text-white hover:bg-sky-900">Open original PDF <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>
        </div>
    </div>
    <div class="mx-auto grid max-w-6xl items-start gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:px-8 lg:py-14">
        <aside class="rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-28">
            <nav aria-label="Prospectus contents">
                <h2 class="text-sm font-black uppercase tracking-wider text-sky-800">In this prospectus</h2>
                <ol class="mt-4 grid grid-cols-2 gap-1 lg:grid-cols-1">
                    @foreach($chapters as $id => $label)
                        <li><a href="#{{ $id }}" class="block rounded-lg px-2 py-2 text-sm font-medium text-slate-600 hover:bg-sky-50 hover:text-sky-900">{{ $label }}</a></li>
                    @endforeach
                </ol>
            </nav>
        </aside>
        <div class="min-w-0 space-y-8">
            <section id="welcome" class="prospectus-section">
                <p class="prospectus-kicker">Welcome message</p>
                <h2>A community where children thrive</h2>
                <div class="mt-6 flex items-center gap-4"><img src="{{ asset('images/watersprings/head-of-school.jpg') }}" alt="Adedamola Ogidan, Head of School" width="80" height="96" class="h-24 w-20 rounded-xl object-cover" loading="lazy"><div><h3 class="font-bold">Adedamola Ogidan</h3><p class="text-sm text-sky-700">Head of School</p></div></div>
                <div class="prospectus-copy">
                    <p>Welcome to Watersprings International School, a bubbly and inclusive community where pupils, staff and parents collaborate to enhance a passion for learning and life.</p>
                    <p>I am delighted to introduce you to our school, where we inspire and empower our pupils to be Godly, confident and enterprising individuals. Our dedicated academic and non-academic staff are committed to providing a world-class education that prepares pupils for success.</p>
                    <p>Our curriculum connects through the core learning skills of the 21st century, challenging, critically engaging and extending pupils. Our robust extracurricular programme develops important life skills and personal interests.</p>
                    <p>As you explore our prospectus, I hope you will get a sense of our supportive school community and commitment to excellence. Join us on this exciting journey and discover that this is where your child belongs!</p>
                </div>
            </section>
            <section id="about-school" class="prospectus-section">
                <p class="prospectus-kicker">About Watersprings</p><h2>Purpose-built for learning</h2>
                <p class="mt-3 text-sm font-bold text-sky-700">Dr. Olukayode Babatunde · CEO</p>
                <div class="prospectus-copy"><p>It is with great pleasure that I welcome you to Watersprings International School, Akure. Our school provides quality pre-school and primary education in a safe and serene suburb of the Ijapo area, with convenient access to residential and business districts.</p><p>Our purpose-built campus has modern facilities, including ICT-enabled classrooms, a music department and CCTV monitoring. Small class sizes support personalised learning for every pupil.</p><p>We foster a warm and welcoming environment for parents and pupils. Our curriculum is based on the National Curriculum of England, thoughtfully adapted to the needs of an international community in 21st-century Nigeria.</p></div>
            </section>
            <section id="facilities" class="prospectus-section">
                <p class="prospectus-kicker">Space to discover</p><h2>Our school facilities</h2>
                <div class="prospectus-copy"><p>A well-equipped, stimulating environment supports learning and development. Our facilities, dedicated staff and inclusive community nurture the mind, body and spirit.</p></div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">@foreach($facilities as $name => $description)<article class="rounded-xl bg-sky-50 p-5"><h3 class="font-bold text-sky-900">{{ $name }}</h3><p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $description }}</p></article>@endforeach</div>
            </section>
            <section id="mission-vision" class="prospectus-section">
                <p class="prospectus-kicker">What guides us</p><h2>Mission and vision</h2>
                <div class="prospectus-copy"><h3>Our mission</h3><p>Raising children to be Godly, confident and enterprising leaders.</p><h3>Our vision</h3><p>We aim to prepare children to reach their full potential as responsible citizens by developing the skills, concepts and attitudes necessary for the opportunities and experiences of the future.</p><p>Each child is considered unique and is therefore treated as one. Every person is valued, and every child is known and cared for. Cooperation is preferred to competition. Successes are acknowledged, difficulties are talked through, and no child is left behind.</p></div>
                <ul class="mt-6 flex flex-wrap gap-2" aria-label="Core values">@foreach(['Excellence', 'Uprightness', 'Service', 'Enterprise'] as $value)<li class="rounded-full bg-sky-50 px-4 py-2 text-sm font-bold text-sky-900">{{ $value }}</li>@endforeach</ul>
                <p class="mt-6 border-t border-slate-200 pt-5 font-bold text-sky-800">Our motto: Under the leadership of God.</p>
            </section>
            <section id="ethos" class="prospectus-section">
                <p class="prospectus-kicker">Learning with purpose</p><h2>Our school ethos</h2>
                <div class="prospectus-copy"><p>Watersprings is a community of caring staff and teachers committed to nurturing future leaders. Christian values uphold an education that extends beyond academics.</p><p>Practical learning challenges pupils to become confident, independent thinkers as they learn, play and grow together. Education should kindle a delight in learning and lay the groundwork for a joyful life of service to the world around us.</p><p>Our teachers and staff strive to leave a lasting legacy in the minds and hearts of our pupils.</p></div>
            </section>
            <section id="curriculum" class="prospectus-section">
                <p class="prospectus-kicker">Our school</p><h2>Classes and curriculum</h2>
                <div class="prospectus-copy"><h3>Early Years & Foundation Stage · 0–5 years</h3><p>The foundation stage is a vital building block in a child’s education. We support progression from playgroup through pre-school to the primary years, making it fun and stimulating. Staff training and continuous improvement support high standards of care.</p><p>Skills develop through seven connected areas of learning, providing a practical, integrated curriculum for young children. The areas described in the prospectus are:</p>
                    <ul><li>Personal, Social & Emotional Development</li><li>Communication, Language & Literacy</li><li>Literacy</li><li>Mathematical Development</li><li>Knowledge and Understanding of the World</li><li>Creative Development</li><li>Physical Development</li></ul>
                    <h3>Key Stage 1 · 5–7 years</h3><p>Children move towards a more structured day while remaining actively engaged in activity-based learning. Reading, writing and maths are complemented by a broad, topic-based curriculum.</p><ul><li>Encourage independence and a sense of responsibility.</li><li>Foster spiritual and moral growth.</li><li>Develop language and reasoning skills.</li><li>Promote a positive attitude to school and learning.</li></ul>
                    <h3>Key Stage 2 · 7–11 years</h3><p>A vibrant, practical curriculum offers a broad range of skills and opportunities, from mathematics and cookery to scientific investigations. Speaking and listening play an important role in literacy, supported by ICT equipment such as interactive whiteboards, class presentations and role play.</p><p>Year 5 numeracy builds on number skills and data handling while preparing pupils for Year 6. At the end of Year 6, children apply to secondary schools of their choice and sit their entrance examinations.</p><p>Science includes the Earth, Sun and Moon; changing state; life cycles; keeping healthy; and gases around us. Pupils plan and carry out investigations in teams, using their findings to form conclusions.</p>
                    <h3>Subjects in Key Stages 1 and 2</h3><ul class="sm:columns-2">@foreach($subjects as $subject)<li>{{ $subject }}</li>@endforeach</ul>
                </div>
                <h3 class="mt-8 text-lg font-bold">Find your child’s class</h3>
                <div class="mt-4 space-y-5">@foreach($classes as $stage => $rows)<div class="overflow-hidden rounded-xl border border-slate-200"><table class="w-full text-left text-sm"><caption class="bg-sky-50 px-4 py-3 text-left font-bold text-sky-900">{{ $stage }}</caption><thead><tr class="border-b border-slate-200"><th scope="col" class="px-4 py-3">Class</th><th scope="col" class="px-4 py-3">Age</th></tr></thead><tbody>@foreach($rows as [$class, $age])<tr class="border-b border-slate-100 last:border-0"><th scope="row" class="px-4 py-3 font-medium">{{ $class }}</th><td class="px-4 py-3 text-slate-600">{{ $age }}</td></tr>@endforeach</tbody></table></div>@endforeach</div>
            </section>
            <section id="school-day" class="prospectus-section">
                <p class="prospectus-kicker">Everyday routines</p><h2>Times of the day</h2>
                <div class="mt-6 overflow-hidden rounded-xl border border-slate-200"><table class="w-full text-left text-sm"><caption class="sr-only">Daily timetable from the school prospectus</caption><thead class="bg-sky-50 text-sky-900"><tr><th scope="col" class="px-4 py-3">Time</th><th scope="col" class="px-4 py-3">Activity</th></tr></thead><tbody>@foreach($timetable as [$time, $activity])<tr class="border-t border-slate-200"><th scope="row" class="px-4 py-4 font-medium">{{ $time }}</th><td class="px-4 py-4 text-slate-600">{{ $activity }}</td></tr>@endforeach</tbody></table></div>
            </section>
            <section id="uniforms" class="prospectus-section">
                <p class="prospectus-kicker">A sense of belonging</p><h2>School uniforms</h2>
                <div class="prospectus-copy"><p>Uniforms encourage pride, unity and a positive attitude to school. Taking care with appearance reflects the responsibility and high standards expected in learning and behaviour.</p><p>After enrolment, each pupil and their guardian visit the uniform room for a personalised fitting. Once the correct sizes are confirmed, the family receives a detailed quote before purchasing.</p></div>
                @php
                    $uniforms = [
                        'Boys’ uniform' => [['Trousers', '2'], ['Shirts', '2'], ['Jumper', '1'], ['Assignment bag', '1'], ['Tie', '1'], ['School socks', '5 pairs'], ['P.E. shorts', '1'], ['P.E. vest', '1 piece'], ['P.E. socks', '1']],
                        'Girls’ uniform' => [['Pinafore', '2'], ['Blouse', '2'], ['Jumper', '1'], ['Assignment bag', '1'], ['Tie', '1'], ['School socks', '3 pairs'], ['P.E. shorts', '1'], ['P.E. vest', '1 piece'], ['P.E. socks', '1'], ['Cotton tights (Year 6)', '2 pairs'], ['Skirt (Year 6)', '2']],
                    ];
                @endphp
                <div class="mt-6 grid items-start gap-4 xl:grid-cols-2">
                    @foreach($uniforms as $label => $items)
                        <div class="overflow-hidden rounded-xl border border-slate-200"><table class="w-full text-left text-sm"><caption class="bg-sky-50 px-4 py-3 text-left font-bold text-sky-900">{{ $label }}</caption><thead><tr class="border-b border-slate-200"><th scope="col" class="px-4 py-3">Item</th><th scope="col" class="px-4 py-3">Quantity</th></tr></thead><tbody>@foreach($items as [$item, $quantity])<tr class="border-b border-slate-100 last:border-0"><th scope="row" class="px-4 py-3 font-medium">{{ $item }}</th><td class="px-4 py-3 text-slate-600">{{ $quantity }}</td></tr>@endforeach</tbody></table></div>
                    @endforeach
                </div>
                <p class="mt-5 text-sm leading-relaxed text-slate-600">For additional items, purchases or specific requests, please contact the school or visit the uniform store.</p>
            </section>
            <section id="attendance" class="prospectus-section">
                <p class="prospectus-kicker">Being here matters</p><h2>Attendance</h2>
                <div class="prospectus-copy"><h3>Regular attendance</h3><p>Consistent attendance supports academic and social progress, builds routines, maintains continuity in learning and helps children form strong relationships with peers and teachers.</p><h3>School hours</h3><p>The attendance policy in the prospectus states that the school day begins at 7:00 am and ends at 3:30 pm, with pupils expected to be ready by 7:00 am. The daily timetable above also lists the drop-off and collection windows. Contact the school office to confirm arrival and collection arrangements for your child’s class.</p><h3>Absences</h3><ul><li><strong>Excused absences:</strong> illness, medical appointments, family emergencies, religious observances or other reasons approved by the school.</li><li><strong>Unexcused absences:</strong> holidays, trips or other absences not authorised by the school.</li><li>Parents or guardians must notify the school by 7:00 am on the day of absence, or beforehand, and provide a written explanation. Without this, the absence will be recorded as unexcused.</li></ul></div>
            </section>
            <section id="transport" class="prospectus-section">
                <p class="prospectus-kicker">Getting to school</p><h2>Bus service</h2>
                <div class="prospectus-copy"><p>The school offers a bus service designed around safety and convenience for families.</p><h3>Safety first</h3><p>Licensed, experienced drivers undergo background checks. Buses have seat belts and first-aid kits and receive regular maintenance.</p><h3>Pick-up and drop-off</h3><p>Key routes have designated stops, with schedules shared with parents. Pupils should be at their stop at least 10 minutes before pick-up.</p><h3>Bus etiquette</h3><p>Pupils must remain seated, wear their seat belts and follow the driver’s or bus monitor’s instructions. Disruptive behaviour may lead to a review of bus privileges.</p><h3>Parents’ responsibilities</h3><p>Ensure your child arrives at the stop on time. A parent or guardian must receive younger children at drop-off; otherwise, the child will be returned to school.</p><h3>Registration and fees</h3><p>Complete the bus-service registration form through the school office or website. Fees are payable termly or annually. Contact the school office for routes, current rates and registration assistance.</p></div>
                <a href="tel:+2349139345577" class="mt-6 inline-block rounded-xl bg-sky-800 px-5 py-3 text-sm font-bold text-white hover:bg-sky-900">Ask about the school bus</a>
            </section>
            <section id="clubs" class="prospectus-section">
                <p class="prospectus-kicker">Beyond the classroom</p><h2>Clubs and extracurricular activities</h2>
                <div class="prospectus-copy"><p>Our clubs help children discover interests and develop social skills, critical thinking and problem-solving abilities that support their growth as future leaders.</p></div>
                <ul class="mt-6 grid gap-3 sm:grid-cols-2">@foreach($clubs as $club)<li class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 font-semibold text-sky-900">{{ $club }}</li>@endforeach</ul>
            </section>
            <section id="prospectus-contact" class="prospectus-section">
                <p class="prospectus-kicker">Let’s talk</p><h2>Contact Watersprings</h2>
                <address class="prospectus-copy not-italic"><p>Plot 6 & 7, Block 17, Ijomu Quarters, Otenioro Layout, Ijapo, Akure, Ondo State, Nigeria.</p><p><a href="tel:+2349139345577" class="font-bold text-sky-800 hover:underline">091 3934 5577</a><br><a href="mailto:admin@waterspringsschool.com.ng" class="break-words font-bold text-sky-800 hover:underline">admin@waterspringsschool.com.ng</a></p><p>Social media: <strong>@wsisakure</strong></p><p><a href="http://www.waterspringsschool.com.ng/" target="_blank" rel="noopener noreferrer" class="break-words text-sky-800 underline">www.waterspringsschool.com.ng</a></p></address>
                <div class="mt-6 flex flex-wrap gap-3"><a href="{{ route('admission') }}" class="rounded-xl bg-sky-800 px-5 py-3 text-sm font-bold text-white hover:bg-sky-900">Explore admissions</a><a href="{{ route('contact') }}#visit" class="rounded-xl border border-sky-200 px-5 py-3 text-sm font-bold text-sky-800 hover:bg-sky-50">Arrange a visit</a></div>
            </section>
            <p class="text-sm leading-relaxed text-slate-500">Adapted for web reading from the Watersprings International School prospectus. <a href="{{ $publicSiteSettings['prospectus_url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-sky-800 underline">View the original document</a> for its full illustrated layout.</p>
        </div>
    </div>
</div>
@endsection
