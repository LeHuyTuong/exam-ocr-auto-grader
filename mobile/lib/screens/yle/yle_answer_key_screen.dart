import 'package:flutter/material.dart';
import '../../models/yle_models.dart';
import '../../services/yle_service.dart';
import '../../utils/error_utils.dart';

class YleAnswerKeyScreen extends StatefulWidget {
  const YleAnswerKeyScreen({super.key});

  @override
  State<YleAnswerKeyScreen> createState() => _YleAnswerKeyScreenState();
}

class _YleAnswerKeyScreenState extends State<YleAnswerKeyScreen> {
  final _service = YleService();
  List<YleExam> _exams = [];
  bool _loading = true;
  String? _selectedLevel;
  String? _selectedSkill;

  @override
  void initState() {
    super.initState();
    _loadExams();
  }

  Future<void> _loadExams() async {
    setState(() => _loading = true);
    try {
      _exams = await _service.getExams(
        level: _selectedLevel,
        skill: _selectedSkill,
      );
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _createExam() async {
    final level = _selectedLevel ?? 'starters';
    final skill = _selectedSkill ?? 'listening';
    try {
      await _service.createExam(level, skill);
      _loadExams();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã tạo paper mới')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Answer Key Editor')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: _selectedLevel,
                    decoration: const InputDecoration(labelText: 'Level'),
                    items: const [
                      DropdownMenuItem(value: 'starters', child: Text('Starters')),
                      DropdownMenuItem(value: 'movers', child: Text('Movers')),
                      DropdownMenuItem(value: 'flyers', child: Text('Flyers')),
                    ],
                    onChanged: (v) {
                      _selectedLevel = v;
                      _loadExams();
                    },
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: _selectedSkill,
                    decoration: const InputDecoration(labelText: 'Skill'),
                    items: const [
                      DropdownMenuItem(value: 'listening', child: Text('Listening')),
                      DropdownMenuItem(value: 'reading_writing', child: Text('R&W')),
                      DropdownMenuItem(value: 'speaking', child: Text('Speaking')),
                    ],
                    onChanged: (v) {
                      _selectedSkill = v;
                      _loadExams();
                    },
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: ElevatedButton.icon(
              onPressed: _createExam,
              icon: const Icon(Icons.add),
              label: const Text('Tạo paper mới (auto-scaffold)'),
            ),
          ),
          const SizedBox(height: 16),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _exams.isEmpty
                    ? const Center(child: Text('Chưa có exam nào'))
                    : ListView.builder(
                        itemCount: _exams.length,
                        itemBuilder: (ctx, i) => _ExamCard(
                          exam: _exams[i],
                          service: _service,
                          onChanged: _loadExams,
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

class _ExamCard extends StatefulWidget {
  final YleExam exam;
  final YleService service;
  final VoidCallback onChanged;

  const _ExamCard({
    required this.exam,
    required this.service,
    required this.onChanged,
  });

  @override
  State<_ExamCard> createState() => _ExamCardState();
}

class _ExamCardState extends State<_ExamCard> {
  bool _expanded = false;

  Future<void> _saveAnswer(YleQuestion question, String answer, List<String> variants) async {
    try {
      await widget.service.updateQuestion(
        question.id,
        correctAnswer: answer.isEmpty ? null : answer,
        acceptedVariants: variants.isEmpty ? null : variants,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã lưu đáp án')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Lỗi: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final exam = widget.exam;
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: ExpansionTile(
        title: Text('${exam.name} (${exam.level}/${exam.skill})'),
        subtitle: Text('${exam.totalMarks} marks, ${exam.totalPages} pages'),
        initiallyExpanded: _expanded,
        onExpansionChanged: (v) => setState(() => _expanded = v),
        children: exam.parts.map((part) {
          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Part ${part.partNumber}: ${part.title}',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                if (part.isAutoGradable)
                  ...part.questions.map((q) => _AnswerInput(
                    question: q,
                    onSave: (answer, variants) => _saveAnswer(q, answer, variants),
                  ))
                else
                  const Padding(
                    padding: EdgeInsets.all(8),
                    child: Text('(Manual grading - no answer key needed)',
                        style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic)),
                  ),
              ],
            ),
          );
        }).toList(),
      ),
    );
  }
}

class _AnswerInput extends StatefulWidget {
  final YleQuestion question;
  final Function(String answer, List<String> variants) onSave;

  const _AnswerInput({required this.question, required this.onSave});

  @override
  State<_AnswerInput> createState() => _AnswerInputState();
}

class _AnswerInputState extends State<_AnswerInput> {
  late TextEditingController _answerCtrl;
  late TextEditingController _variantsCtrl;

  @override
  void initState() {
    super.initState();
    _answerCtrl = TextEditingController(text: widget.question.correctAnswer ?? '');
    _variantsCtrl = TextEditingController(
      text: widget.question.acceptedVariants.join(', '),
    );
  }

  @override
  void dispose() {
    _answerCtrl.dispose();
    _variantsCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          SizedBox(
            width: 40,
            child: Text('Q${widget.question.questionNumber}:',
                style: const TextStyle(fontWeight: FontWeight.w500)),
          ),
          Expanded(
            child: TextField(
              controller: _answerCtrl,
              decoration: const InputDecoration(
                hintText: 'Correct answer',
                isDense: true,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: TextField(
              controller: _variantsCtrl,
              decoration: const InputDecoration(
                hintText: 'Variants (comma-separated)',
                isDense: true,
              ),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.save, size: 20),
            onPressed: () {
              final variants = _variantsCtrl.text
                  .split(',')
                  .map((v) => v.trim())
                  .where((v) => v.isNotEmpty)
                  .toList();
              widget.onSave(_answerCtrl.text, variants);
            },
          ),
        ],
      ),
    );
  }
}
